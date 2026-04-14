/**
 * CLOUDFLARE WORKER PARA ENCURTOU.PRO
 * Solução para Dynamic Wildcard Subdomains em Hostinger Compartilhada.
 * 
 * Este script captura requisições em *.encurtou.pro e as encaminha 
 * para o domínio principal injetando o subdomínio e a slug via parâmetros.
 */

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const hostname = url.hostname; // Ex: teste.encurtou.pro
    const baseDomain = 'encurtou.pro';
    
    // 1. Se for o domínio principal ou www, deixa passar normal para o servidor
    if (hostname === baseDomain || hostname === `www.${baseDomain}`) {
      return fetch(request);
    }
    
    // 2. Se for um subdomínio (ex: teste.encurtou.pro)
    if (hostname.endsWith(`.${baseDomain}`)) {
      const prefix = hostname.replace(`.${baseDomain}`, '');
      const parts = prefix.split('.');
      
      // Remove 'www' se ele vier antes do subdomínio (ex: www.teste.encurtou.pro)
      if (parts[0] === 'www') parts.shift();
      
      const subdomain = parts[0] || '';
      const slug = url.pathname.slice(1); // Remove a barra inicial '/'
      
      if (subdomain && subdomain !== '') {
        // Constrói a nova URL de destino apontando para o domínio principal que a Hostinger reconhece
        // Injetamos subdomain e slug como parâmetros GET
        const searchParams = url.search ? `&${url.search.slice(1)}` : '';
        const targetUrl = `https://${baseDomain}/redirect.php?subdomain=${subdomain}&slug=${slug}${searchParams}`;
        
        // Criar um novo request mantendo headers originais (importante para IP real, etc)
        const newRequest = new Request(targetUrl, {
          method: request.method,
          headers: request.headers,
          body: request.body
        });
        
        return fetch(newRequest);
      }
    }
    
    // Fallback padrão
    return fetch(request);
  }
};

/**
 * INSTRUÇÕES DE INSTALAÇÃO:
 * 1. No painel da Cloudflare, vá em 'Workers & Pages' -> 'Create Application' -> 'Create Worker'.
 * 2. Dê um nome (ex: encurtou-proxy) e clique em 'Deploy'.
 * 3. Clique em 'Edit Code', apague tudo e cole o código acima. 'Save and Deploy'.
 * 4. Volte para a página do Worker e clique na aba 'Triggers'.
 * 5. Em 'Routes', clique em 'Add Route'.
 * 6. Digite: *.encurtou.pro/*
 * 7. Selecione a zona 'encurtou.pro' e salve.
 */
