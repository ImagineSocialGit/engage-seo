<?php

namespace App\Support\Seo;

final class PageFaqStructuredDataContributor implements \App\Contracts\Seo\StructuredDataContributor
{
    public function structuredData(array $page, array $site): array
    {
        $sections = $page['sections'] ?? [];

        if (! is_array($sections)) {
            return [];
        }

        $questions = [];

        foreach ($sections as $section) {
            if (! is_array($section)
                || ($section['component'] ?? null) !== 'faq'
            ) {
                continue;
            }

            $items = $section['props']['items'] ?? [];

            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $question = $this->text($item['question'] ?? null);
                $answer = $this->answer($item['answer'] ?? null);

                if ($question === null || $answer === null) {
                    continue;
                }

                $questions[] = [
                    '@type' => 'Question',
                    'name' => $question,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $answer,
                    ],
                ];
            }
        }

        if ($questions === []) {
            return [];
        }

        return [[
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $questions,
        ]];
    }

    private function answer(mixed $value): ?string
    {
        if (is_string($value)) {
            return $this->text($value);
        }

        if (! is_array($value) || ! array_is_list($value)) {
            return null;
        }

        $paragraphs = [];

        foreach ($value as $paragraph) {
            $text = $this->text($paragraph);

            if ($text !== null) {
                $paragraphs[] = $text;
            }
        }

        return $paragraphs !== []
            ? implode("\n\n", $paragraphs)
            : null;
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }
}