<?php

namespace App\Features\Forms\Services;

use App\Integrations\EngageCore\Data\PublishedForm;
use InvalidArgumentException;

final class SiteFormBindings
{
    private const SUPPORTED_TYPES = [
        'text', 'email', 'tel', 'url', 'number', 'date',
        'textarea', 'select', 'radio', 'checkbox', 'boolean', 'checkboxes',
    ];

    /** @return array<string, mixed>|null */
    public function get(string $formKey): ?array
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/D', $formKey) !== 1) {
            return null;
        }

        $binding = config('site_forms.bindings.'.$formKey);

        return is_array($binding) && ($binding['enabled'] ?? false) === true
            ? $binding
            : null;
    }

    public function supports(PublishedForm $form): bool
    {
        if (! $form->isPublic || $form->fields === []) {
            return false;
        }

        // Do not silently discard fields or claim to implement conditional forms.
        if (($form->layout['conditions'] ?? []) !== []) {
            return false;
        }

        foreach ($form->fields as $field) {
            $type = $field['type'] ?? null;
            if (! is_string($type) || ! in_array($type, self::SUPPORTED_TYPES, true)) {
                return false;
            }
            if (in_array($type, ['select', 'radio', 'checkboxes'], true)) {
                $options = $field['options'] ?? null;
                if (! is_array($options) || $options === []) {
                    return false;
                }
                foreach ($options as $option) {
                    if (! is_array($option)
                        || ! is_string($option['value'] ?? null)
                        || ! is_string($option['label'] ?? null)
                    ) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function values(PublishedForm $form, mixed $input): array
    {
        if (! is_array($input) || array_is_list($input)) {
            // Empty form data is an empty object in PHP's request payload.
            if ($input !== []) {
                throw new InvalidArgumentException('Form values must be an object.');
            }
        }

        $input = is_array($input) ? $input : [];
        $keys = array_fill_keys($form->fieldKeys(), true);

        if (array_diff(array_keys($input), array_keys($keys)) !== []) {
            throw new InvalidArgumentException('Unknown form values are not accepted.');
        }

        $values = [];
        foreach ($form->fields as $field) {
            $key = (string) $field['key'];
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $type = (string) $field['type'];
            $value = $input[$key];

            if ($type === 'checkboxes') {
                if (! is_array($value) || ! array_is_list($value)
                    || count($value) > 100
                ) {
                    throw new InvalidArgumentException('Invalid checkbox selection.');
                }
                $choices = array_column($field['options'], 'value');
                if (array_diff($value, $choices) !== []
                    || count(array_unique($value)) !== count($value)
                ) {
                    throw new InvalidArgumentException('Invalid checkbox selection.');
                }
                $values[$key] = $value;
                continue;
            }

            if (in_array($type, ['boolean', 'checkbox'], true)) {
                if (! in_array($value, [true, false, '1', '0', 1, 0], true)) {
                    throw new InvalidArgumentException('Invalid checkbox value.');
                }
                $values[$key] = in_array($value, [true, '1', 1], true);
                continue;
            }

            if (! is_string($value) || mb_strlen($value) > 5000) {
                throw new InvalidArgumentException('Invalid form value.');
            }
            $value = trim($value);
            if (in_array($type, ['select', 'radio'], true)
                && $value !== ''
                && ! in_array($value, array_column($field['options'], 'value'), true)
            ) {
                throw new InvalidArgumentException('Invalid form selection.');
            }
            $values[$key] = $value;
        }

        return $values;
    }
}