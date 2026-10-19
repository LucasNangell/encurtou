/**
 * LEGADO — não é usado pelo Wrangler. Fonte canônica: cloudflare/worker.js (main em cloudflare/wrangler.toml).
 * Mantido apenas como referência; alterações de produção devem ser feitas em cloudflare/worker.js.
 *
 * Encurtou edge redirect worker.
 * - Origin (PHP+MySQL) is source of truth.
 * - KV accelerates lookup for simple links.
 * - Click ingestion is asynchronous.
 */

const SIGNATURE_MAX_SKEW_MS = 5 * 60 * 1000;

export default {
  async fetch(request, env, ctx) {
    try {
      const url = new URL(request.url);

      if (!isEdgeEnabled(env)) {
        return fallbackToOrigin(request);
      }

      if (url.pathname === "/__edge/invalidate") {
        return handleInvalidateRequest(request, env, ctx);
      }

      if (!["GET", "HEAD"].includes(request.method)) {
        return fallbackToOrigin(request); // Usar fallback para injetar headers
      }

      const { slug } = parseSlug(url.pathname);
      if (!slug || shouldBypassPath(url.pathname)) {
        return fallbackToOrigin(request); // Usar fallback para injetar headers
      }

      if (!isCanaryAllowed(slug, env)) {
        return fallbackToOrigin(request);
      }

      const subdomain = extractSubdomain(url.hostname, env.ROOT_DOMAIN);
      const cacheKey = `slug:${subdomain}:${slug}`;

      let cacheMeta = await readJsonKV(env.SLUG_CACHE, cacheKey);
      let resolved = null;
      let cacheStatus = "MISS";

      if (cacheMeta && Number(cacheMeta.expires_at_ms || 0) > Date.now()) {
        const versionedKey = `${cacheKey}:v:${cacheMeta.cache_version}`;
        resolved = await readJsonKV(env.SLUG_CACHE, versionedKey);
        if (resolved && !mustBypassCache(resolved)) {
          cacheStatus = "HIT";
        } else {
          resolved = null;
        }
      }

      if (!resolved) {
        resolved = await resolveFromBackend(request, env, slug, subdomain);
        if (!resolved) {
          return fallbackToOrigin(request);
        }
        cacheStatus = "MISS";
        ctx.waitUntil(writeResolutionCache(env, cacheKey, resolved));
      }

      // Keep legacy behavior for advanced flows managed in PHP.
      if (resolved.force_origin) {
        return fallbackToOrigin(request);
      }

      if (resolved.action === "not_found") {
        return new Response("Not Found", {
          status: 404,
          headers: {
            "Cache-Control": "no-store",
            "X-Edge-Cache": cacheStatus,
          },
        });
      }

      if (resolved.action !== "redirect" || !resolved.destination_url) {
        return fallbackToOrigin(request);
      }

      const safeDestination = ensureSafeDestination(resolved.destination_url);
      if (!safeDestination) {
        return fallbackToOrigin(request);
      }

      const clickId = crypto.randomUUID();
      const passThrough = extractPassThroughSearchParams(url);
      const mergedDestination = mergePassThroughOntoDestination(safeDestination, passThrough);
      const withTracking = withTrackingParam(
        mergedDestination,
        resolved.tracking_enabled || resolved.monitor_dwell_enabled,
        clickId
      );
      const redirectTarget = withDwellMonitorParam(withTracking, resolved.monitor_dwell_enabled);
      const event = buildClickEvent(request, resolved, { slug, subdomain, clickId, passThrough });

      if (env.CLICK_QUEUE) {
        ctx.waitUntil(env.CLICK_QUEUE.send(event));
      } else {
        ctx.waitUntil(sendClickToBackend(event, env));
      }

      const statusCode = resolved.redirect_type === "301" ? 301 : 302;
      return new Response(null, {
        status: statusCode,
        headers: {
          Location: redirectTarget,
          "Cache-Control": "no-store",
          "X-Edge-Cache": cacheStatus,
          "X-Edge-Mode": resolved.ab_backend_strict ? "ab-backend" : "direct",
        },
      });
    } catch (error) {
      return fallbackToOrigin(request);
    }
  },

  async queue(batch, env) {
    for (const message of batch.messages) {
      try {
        await sendClickToBackend(message.body, env);
        message.ack();
      } catch (_error) {
        message.retry();
      }
    }
  },
};

function shouldBypassPath(pathname) {
  if (!pathname || pathname === "/") return true;
  return (
    pathname.startsWith("/api/") ||
    pathname.startsWith("/assets/") ||
    pathname.startsWith("/uploads/") ||
    pathname.startsWith("/billing/") ||
    pathname.startsWith("/__edge/")
  );
}

function isEdgeEnabled(env) {
  return String(env.EDGE_REDIRECT_ENABLED || "0") === "1";
}

function isCanaryAllowed(slug, env) {
  const slugs = String(env.EDGE_CANARY_SLUGS || "")
    .split(",")
    .map((v) => v.trim().toLowerCase())
    .filter(Boolean);

  if (slugs.length > 0) {
    return slugs.includes(slug.toLowerCase());
  }

  const percentRaw = Number(env.EDGE_CANARY_PERCENT || 100);
  const percent = Number.isFinite(percentRaw) ? Math.max(0, Math.min(100, percentRaw)) : 100;
  if (percent >= 100) return true;
  if (percent <= 0) return false;

  const bucket = stableHashBucket(slug);
  return bucket < percent;
}

function stableHashBucket(value) {
  let hash = 2166136261;
  for (let i = 0; i < value.length; i += 1) {
    hash ^= value.charCodeAt(i);
    hash += (hash << 1) + (hash << 4) + (hash << 7) + (hash << 8) + (hash << 24);
  }
  return Math.abs(hash >>> 0) % 100;
}

function parseSlug(pathname) {
  const clean = pathname.replace(/^\/+|\/+$/g, "");
  return { slug: clean.toLowerCase() };
}

function extractSubdomain(host, rootDomain) {
  const normalizedHost = host.toLowerCase();
  const normalizedRoot = (rootDomain || "").toLowerCase();
  if (!normalizedRoot) return "ROOT";
  if (normalizedHost === normalizedRoot || normalizedHost === `www.${normalizedRoot}`) {
    return "ROOT";
  }
  if (!normalizedHost.endsWith(`.${normalizedRoot}`)) {
    return "ROOT";
  }
  const prefix = normalizedHost.slice(0, normalizedHost.length - normalizedRoot.length - 1);
  const first = prefix.split(".")[0];
  return first ? first : "ROOT";
}

function mustBypassCache(payload) {
  return Boolean(payload.force_origin || payload.ab_backend_strict || payload.cacheable === false);
}

async function writeResolutionCache(env, cacheKey, resolved) {
  if (!env.SLUG_CACHE || !resolved || !resolved.cacheable) return;

  const ttl = clampTTL(resolved.ttl_seconds ?? 60, 10, 900);
  const now = Date.now();
  const cacheVersion = String(resolved.cache_version || `ts-${now}`);
  const versionedKey = `${cacheKey}:v:${cacheVersion}`;

  const record = {
    ...resolved,
    cached_at: new Date(now).toISOString(),
    expires_at_ms: now + ttl * 1000,
  };

  await Promise.all([
    env.SLUG_CACHE.put(versionedKey, JSON.stringify(record), { expirationTtl: ttl }),
    env.SLUG_CACHE.put(
      cacheKey,
      JSON.stringify({
        cache_version: cacheVersion,
        expires_at_ms: now + ttl * 1000,
      }),
      { expirationTtl: ttl }
    ),
  ]);
}

async function readJsonKV(kv, key) {
  if (!kv || !key) return null;
  return kv.get(key, { type: "json" });
}

function clampTTL(value, min, max) {
  const parsed = Number(value);
  if (Number.isNaN(parsed)) return min;
  return Math.max(min, Math.min(max, parsed));
}

function ensureSafeDestination(raw) {
  try {
    const parsed = new URL(raw);
    if (!["http:", "https:"].includes(parsed.protocol)) {
      return null;
    }
    return parsed.toString();
  } catch {
    return null;
  }
}

function edgePassThroughReservedKeys() {
  return new Set(["slug", "subdomain", "src", "debug", "aff", "affiliate", "ep_cid"]);
}

function extractPassThroughSearchParams(requestUrl) {
  const reserved = edgePassThroughReservedKeys();
  const out = new URLSearchParams();
  for (const [key, value] of requestUrl.searchParams) {
    if (reserved.has(key.toLowerCase())) continue;
    out.append(key, value);
  }
  return out;
}

function mergePassThroughOntoDestination(destination, passThroughParams) {
  if (!passThroughParams || [...passThroughParams.keys()].length === 0) return destination;
  try {
    const dest = new URL(destination);
    for (const [key, value] of passThroughParams) {
      dest.searchParams.set(key, value);
    }
    return dest.toString();
  } catch {
    return destination;
  }
}

function withTrackingParam(destination, trackingEnabled, clickId) {
  if (!trackingEnabled) return destination;
  try {
    const url = new URL(destination);
    url.searchParams.set("ep_cid", clickId);
    return url.toString();
  } catch {
    return destination;
  }
}

function withDwellMonitorParam(destination, monitorEnabled) {
  if (!monitorEnabled) return destination;
  try {
    const url = new URL(destination);
    url.searchParams.set("ep_dm", "1");
    return url.toString();
  } catch {
    return destination;
  }
}

function buildClickEvent(request, resolved, context) {
  const requestUrl = new URL(request.url);
  const passThrough = context.passThrough || new URLSearchParams();
  let incomingQuery = passThrough.toString();
  if (incomingQuery.length > 2048) incomingQuery = incomingQuery.slice(0, 2048);
  const event = {
    click_id: context.clickId,
    ts: new Date().toISOString(),
    slug: context.slug,
    subdomain: context.subdomain,
    link_id: resolved.link_id || null,
    variant_id: resolved.variant_id || null,
    source: requestUrl.searchParams.get("src") === "qr" ? "qr" : "digital",
    country: request.cf?.country || null,
    region: request.cf?.region || null,
    city: request.cf?.city || null,
    ua: request.headers.get("user-agent") || "",
    referer: request.headers.get("referer") || "Acesso Direto",
    ip: request.headers.get("CF-Connecting-IP") || "",
  };
  if (incomingQuery !== "") {
    event.incoming_query = incomingQuery;
  }
  return event;
}

async function resolveFromBackend(request, env, slug, subdomain) {
  const payload = {
    slug,
    subdomain,
    ua: request.headers.get("user-agent") || "",
    referer: request.headers.get("referer") || "",
    country: request.cf?.country || null,
    ip: request.headers.get("CF-Connecting-IP") || "",
    request_ts: Date.now(),
  };

  const body = JSON.stringify(payload);
  const timestamp = String(Math.floor(Date.now() / 1000));
  const signature = await signHmacHex(body, env.EDGE_SHARED_SECRET || "");

  const resp = await fetch(`${env.BACKEND_BASE_URL}/api/edge/resolve.php`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Edge-Key": env.EDGE_API_KEY || "",
      "X-Edge-Signature": signature,
      "X-Edge-Timestamp": timestamp,
    },
    body,
    cf: { cacheTtl: 0, cacheEverything: false },
  });

  if (resp.status === 404) {
    return {
      action: "not_found",
      cacheable: true,
      ttl_seconds: 20,
      force_origin: false,
      cache_version: `nf-${Date.now()}`,
    };
  }

  if (!resp.ok) return null;
  const data = await resp.json();
  return data && typeof data === "object" ? data : null;
}

async function sendClickToBackend(event, env) {
  const body = JSON.stringify(event);
  const timestamp = String(Math.floor(Date.now() / 1000));
  const signature = await signHmacHex(body, env.EDGE_SHARED_SECRET || "");

  const resp = await fetch(`${env.BACKEND_BASE_URL}/api/edge/click.php`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Edge-Key": env.EDGE_API_KEY || "",
      "X-Edge-Signature": signature,
      "X-Edge-Timestamp": timestamp,
    },
    body,
    cf: { cacheTtl: 0, cacheEverything: false },
  });

  if (!resp.ok) {
    throw new Error(`click_ingest_failed:${resp.status}`);
  }
}

async function signHmacHex(payload, secret) {
  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"]
  );
  const signature = await crypto.subtle.sign("HMAC", key, new TextEncoder().encode(payload));
  return [...new Uint8Array(signature)].map((b) => b.toString(16).padStart(2, "0")).join("");
}

/**
 * Fallback para a Origem com Injeção de Geolocalização robusta.
 */
function fallbackToOrigin(request) {
  const newHeaders = new Headers(request.headers);
  if (request.cf) {
    if (request.cf.city) newHeaders.set('X-JS-IPCity', request.cf.city);
    if (request.cf.region) newHeaders.set('X-JS-IPRegion', request.cf.region);
    if (request.cf.regionCode) newHeaders.set('X-JS-IPRegion-Code', request.cf.regionCode);
    if (request.cf.country) newHeaders.set('X-JS-IPCountry', request.cf.country);
  }
  return fetch(new Request(request, { headers: newHeaders }), {
    cf: { cacheTtl: 0, cacheEverything: false }
  });
}

async function handleInvalidateRequest(request, env) {
  if (request.method !== "POST") {
    return new Response(JSON.stringify({ ok: false, error: "method_not_allowed" }), {
      status: 405,
      headers: { "Content-Type": "application/json; charset=utf-8" },
    });
  }

  const signature = request.headers.get("X-Edge-Invalidate-Signature") || "";
  const timestamp = request.headers.get("X-Edge-Invalidate-Timestamp") || "";
  const bodyText = await request.text();

  if (!signature || !timestamp || !env.EDGE_WORKER_INVALIDATE_SECRET) {
    return jsonResponse(401, { ok: false, error: "missing_auth" });
  }

  const ts = Number(timestamp) * 1000;
  if (!Number.isFinite(ts) || Math.abs(Date.now() - ts) > SIGNATURE_MAX_SKEW_MS) {
    return jsonResponse(401, { ok: false, error: "timestamp_out_of_window" });
  }

  const expected = await signHmacHex(bodyText, env.EDGE_WORKER_INVALIDATE_SECRET);
  if (expected !== signature.toLowerCase()) {
    return jsonResponse(401, { ok: false, error: "invalid_signature" });
  }

  let payload = {};
  try {
    payload = JSON.parse(bodyText);
  } catch {
    return jsonResponse(400, { ok: false, error: "invalid_json" });
  }

  if (!env.SLUG_CACHE) {
    return jsonResponse(500, { ok: false, error: "kv_not_bound" });
  }

  const scope = String(payload.scope || "slug");
  const subdomain = String(payload.subdomain || "ROOT");
  const slug = String(payload.slug || "");
  if (scope === "slug") {
    if (!slug) return jsonResponse(400, { ok: false, error: "slug_required" });
    const cacheKey = `slug:${subdomain}:${slug}`;
    const cacheMeta = await readJsonKV(env.SLUG_CACHE, cacheKey);
    const versionedKey = cacheMeta?.cache_version ? `${cacheKey}:v:${cacheMeta.cache_version}` : null;
    await env.SLUG_CACHE.delete(cacheKey);
    if (versionedKey) {
      await env.SLUG_CACHE.delete(versionedKey);
    }
    return jsonResponse(200, {
      ok: true,
      scope,
      deleted_keys: versionedKey ? [cacheKey, versionedKey] : [cacheKey],
    });
  }

  return jsonResponse(400, {
    ok: false,
    error: "unsupported_scope",
    supported_scopes: ["slug"],
  });
}

function jsonResponse(status, payload) {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { "Content-Type": "application/json; charset=utf-8", "Cache-Control": "no-store" },
  });
}
