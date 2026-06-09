<?php

namespace App\Http\Requests\Page;

use App\Models\Page;
use App\Support\HtmlSanitizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePageRequest extends FormRequest
{
    public const ALLOWED_BLOCK_TYPES = [
        'HeadingBlock',
        'TextBlock',
        'ButtonBlock',
        'ImageBlock',
        'VideoBlock',
        'ColumnsBlock',
        'GridBlock',
        'DividerBlock',
        'SpacerBlock',
        'PricingBlock',
        'FeatureBlock',
        'TestimonialsBlock',
        'HtmlBlock',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Page $page */
        $page = $this->route('page');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('pages', 'slug')->ignore($page->id),
            ],
            'status' => ['sometimes', Rule::in([Page::STATUS_DRAFT, Page::STATUS_PUBLISHED])],
            'puck_data' => ['sometimes', 'nullable', 'array'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * Validate each block type against the whitelist after the standard rules pass.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $puck = $this->input('puck_data');
            if (! is_array($puck)) {
                return;
            }

            $content = $puck['content'] ?? null;
            if (! is_array($content)) {
                return;
            }

            foreach ($content as $i => $block) {
                if (! is_array($block) || ! isset($block['type'])) {
                    continue;
                }
                if (! in_array($block['type'], self::ALLOWED_BLOCK_TYPES, true)) {
                    $v->errors()->add("puck_data.content.{$i}.type", "Tipo de bloque '{$block['type']}' no permitido.");
                }
            }
        });
    }

    /**
     * Sanitize HTML content inside puck_data blocks before persistence.
     * Preserves the original array order of puck_data keys.
     */
    public function sanitizedPuckData(): ?array
    {
        $data = $this->validated();

        if (! isset($data['puck_data']) || ! is_array($data['puck_data'])) {
            return $data['puck_data'] ?? null;
        }

        $puck = $data['puck_data'];

        if (isset($puck['content']) && is_array($puck['content'])) {
            $puck['content'] = $this->sanitizeBlocks($puck['content']);
        }

        return $puck;
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @return array<int, mixed>
     */
    protected function sanitizeBlocks(array $blocks): array
    {
        foreach ($blocks as $i => $block) {
            if (! is_array($block)) {
                continue;
            }

            $props = $block['props'] ?? [];
            if (! is_array($props)) {
                continue;
            }

            if (isset($props['content']) && is_string($props['content'])) {
                $props['content'] = $this->normalizeHtml($props['content']);
            }
            if (isset($props['children']) && is_string($props['children'])) {
                $props['children'] = $this->normalizePlainText($props['children']);
            }
            if (isset($props['url']) && is_string($props['url'])) {
                $props['url'] = HtmlSanitizer::safeUrl($props['url']);
            }
            if (isset($props['link_url']) && is_string($props['link_url'])) {
                $props['link_url'] = HtmlSanitizer::safeUrl($props['link_url']);
            }
            if (isset($props['src']) && is_string($props['src'])) {
                $props['src'] = HtmlSanitizer::safeUrl($props['src']);
            }

            $blocks[$i]['props'] = $props;
        }

        return $blocks;
    }

    /**
     * Sanitize full HTML content (TextBlock.content, HtmlBlock.content).
     * Allows HTML tags and removes scripts/event handlers.
     */
    protected function normalizeHtml(string $html): string
    {
        return HtmlSanitizer::safeHtml($html);
    }

    /**
     * Plain text (HeadingBlock.children) - strip ALL HTML and entities.
     */
    protected function normalizePlainText(string $text): string
    {
        $stripped = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $text) ?? $text;
        $stripped = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $stripped) ?? $stripped;
        $stripped = trim(strip_tags($stripped));
        $stripped = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($stripped);
    }
}
