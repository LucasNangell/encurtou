<?php

require_once __DIR__ . '/bio_helpers.php';

function bio_form_allowed_field_types(): array
{
    return [
        'short_text', 'long_text', 'email', 'phone', 'number', 'date', 'time', 'url',
        'select', 'radio', 'checkbox', 'multiselect',
    ];
}

function bio_form_field_key(string $blockId, string $fieldId): string
{
    return $blockId . '|' . $fieldId;
}

function bio_form_collect_fields(array $schema): array
{
    $out = [];
    if (empty($schema['blocks']) || !is_array($schema['blocks'])) {
        return $out;
    }
    foreach ($schema['blocks'] as $block) {
        if (!is_array($block) || ($block['type'] ?? '') !== 'form') {
            continue;
        }
        $blockId = bio_normalize_text($block['id'] ?? '', '');
        if ($blockId === '') {
            continue;
        }
        $items = $block['items'] ?? [];
        if (!is_array($items)) {
            continue;
        }
        foreach ($items as $field) {
            if (!is_array($field)) {
                continue;
            }
            $fid = bio_normalize_text($field['id'] ?? '', '');
            if ($fid === '') {
                continue;
            }
            $key = bio_form_field_key($blockId, $fid);
            $ft = bio_normalize_text($field['field_type'] ?? 'short_text', 'short_text');
            if (!in_array($ft, bio_form_allowed_field_types(), true)) {
                $ft = 'short_text';
            }
            $out[$key] = [
                'block_id' => $blockId,
                'field_id' => $fid,
                'field_type' => $ft,
                'label' => bio_normalize_text($field['label'] ?? '', ''),
                'required' => !empty($field['required']),
                'placeholder' => bio_normalize_text($field['placeholder'] ?? '', ''),
                'help_text' => bio_normalize_text($field['help_text'] ?? '', ''),
                'max_length' => max(0, (int) ($field['max_length'] ?? 0)),
                'mask_pattern' => bio_normalize_text($field['mask_pattern'] ?? '', ''),
                'default_value' => bio_normalize_text($field['default_value'] ?? '', ''),
                'options' => is_array($field['options'] ?? null) ? $field['options'] : [],
                'show_if' => is_array($field['show_if'] ?? null) ? $field['show_if'] : null,
            ];
        }
    }
    return $out;
}

function bio_form_option_values(array $field): array
{
    $allowed = [];
    foreach ($field['options'] as $opt) {
        if (!is_array($opt)) {
            continue;
        }
        $v = bio_normalize_text($opt['value'] ?? '', '');
        if ($v === '') {
            continue;
        }
        $allowed[$v] = bio_normalize_text($opt['label'] ?? '', $v);
    }
    return $allowed;
}

function bio_form_is_chartable(string $fieldType): bool
{
    return in_array($fieldType, ['select', 'radio', 'checkbox', 'multiselect'], true);
}

function bio_form_field_visible(array $field, array $valuesByKey): bool
{
    $cond = $field['show_if'] ?? null;
    if (!is_array($cond)) {
        return true;
    }
    $dep = bio_normalize_text($cond['field_key'] ?? '', '');
    if ($dep === '') {
        return true;
    }
    $op = bio_normalize_text($cond['operator'] ?? 'eq', 'eq');
    $expected = (string) ($cond['value'] ?? '');
    $actual = $valuesByKey[$dep] ?? '';
    if (is_array($actual)) {
        $actual = implode(', ', $actual);
    }
    $actual = (string) $actual;
    if ($op === 'neq') {
        return $actual !== $expected;
    }
    return $actual === $expected;
}

function bio_form_validate_submission(array $fieldsMap, array $rawAnswers): array
{
    $errors = [];
    $valuesByKey = [];

    foreach ($fieldsMap as $key => $field) {
        $type = $field['field_type'];
        if (!in_array($type, bio_form_allowed_field_types(), true)) {
            $type = 'short_text';
        }
        $raw = $rawAnswers[$key] ?? null;
        if ($type === 'checkbox') {
            $valuesByKey[$key] = !empty($raw) && $raw !== '0' && $raw !== 'false' ? '1' : '';
        } elseif ($type === 'multiselect') {
            if ($raw === null || $raw === '') {
                $valuesByKey[$key] = [];
            } elseif (is_array($raw)) {
                $valuesByKey[$key] = array_map('strval', $raw);
            } elseif (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $valuesByKey[$key] = is_array($decoded) ? array_map('strval', $decoded) : [trim($raw)];
            } else {
                $valuesByKey[$key] = [];
            }
        } else {
            if (is_array($raw)) {
                $raw = implode(', ', $raw);
            }
            $valuesByKey[$key] = trim((string) $raw);
        }
    }

    foreach ($fieldsMap as $key => $field) {
        if (!bio_form_field_visible($field, $valuesByKey)) {
            continue;
        }
        $type = $field['field_type'];
        $val = $valuesByKey[$key];
        $label = $field['label'] ?: $key;

        if ($field['required']) {
            if ($type === 'multiselect') {
                if (!is_array($val) || count($val) === 0) {
                    $errors[] = 'Preencha o campo obrigatório: ' . $label;
                }
            } elseif ($type === 'checkbox') {
                if ($val !== '1') {
                    $errors[] = 'Marque o campo obrigatório: ' . $label;
                }
            } elseif ($val === '') {
                $errors[] = 'Preencha o campo obrigatório: ' . $label;
            }
        }

        if ($type === 'multiselect') {
            if (!is_array($val)) {
                continue;
            }
            $allowed = bio_form_option_values($field);
            if (empty($allowed)) {
                continue;
            }
            foreach ($val as $v) {
                if ($v !== '' && !array_key_exists((string) $v, $allowed)) {
                    $errors[] = 'Opção inválida em: ' . $label;
                    break;
                }
            }
        } elseif (in_array($type, ['select', 'radio'], true) && $val !== '') {
            $allowed = bio_form_option_values($field);
            if (!empty($allowed) && !array_key_exists($val, $allowed)) {
                $errors[] = 'Opção inválida em: ' . $label;
            }
        }

        if ($val === '' || $val === []) {
            continue;
        }
        if ($type === 'checkbox' && !$field['required'] && $val !== '1') {
            continue;
        }

        $maxLen = (int) ($field['max_length'] ?? 0);
        $scalarVal = is_array($val) ? implode(', ', $val) : (string) $val;
        if ($maxLen > 0 && mb_strlen($scalarVal) > $maxLen) {
            $errors[] = 'Texto longo demais em: ' . $label;
        }

        switch ($type) {
            case 'email':
                if ($scalarVal !== '' && !filter_var($scalarVal, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'E-mail inválido em: ' . $label;
                }
                break;
            case 'url':
                if ($scalarVal !== '' && !filter_var($scalarVal, FILTER_VALIDATE_URL)) {
                    $errors[] = 'URL inválida em: ' . $label;
                }
                break;
            case 'number':
                if ($scalarVal !== '' && !is_numeric(str_replace(',', '.', $scalarVal))) {
                    $errors[] = 'Número inválido em: ' . $label;
                }
                break;
            case 'phone':
                if ($scalarVal !== '' && strlen(preg_replace('/\D/', '', $scalarVal)) < 8) {
                    $errors[] = 'Telefone inválido em: ' . $label;
                }
                break;
        }
    }

    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors, 'values' => $valuesByKey];
    }

    $normalizedRows = [];
    foreach ($fieldsMap as $key => $field) {
        if (!bio_form_field_visible($field, $valuesByKey)) {
            continue;
        }
        $type = $field['field_type'];
        $val = $valuesByKey[$key];
        $row = [
            'block_id' => $field['block_id'],
            'field_id' => $field['field_id'],
            'field_key' => $key,
            'field_type' => $type,
            'value_text' => null,
            'value_json' => null,
        ];
        if ($type === 'multiselect') {
            $row['value_json'] = json_encode(array_values(is_array($val) ? $val : []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $row['value_text'] = is_array($val) ? implode('; ', $val) : '';
        } elseif ($type === 'checkbox') {
            $row['value_text'] = $val === '1' ? 'Sim' : 'Não';
        } else {
            $row['value_text'] = is_array($val) ? implode(', ', $val) : (string) $val;
        }
        $normalizedRows[] = $row;
    }

    return ['ok' => true, 'errors' => [], 'values' => $valuesByKey, 'rows' => $normalizedRows];
}

function bio_form_rate_limit_hit(PDO $pdo, int $bioPageId, string $ip): bool
{
    if ($ip === '') {
        return false;
    }
    if (!bio_has_table($pdo, 'bio_form_submissions')) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bio_form_submissions WHERE bio_page_id = ? AND submitter_ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
    $stmt->execute([$bioPageId, $ip]);
    $n = (int) $stmt->fetchColumn();
    return $n >= 40;
}

function bio_has_table(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return false;
    }
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    $cache[$table] = (bool) $stmt->fetchColumn();
    return $cache[$table];
}
