<?php

namespace App\Services;

use InvalidArgumentException;

class ConversationTree
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $json): array
    {
        try {
            $tree = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException('El árbol debe ser un JSON válido.');
        }

        if (! is_array($tree)) {
            throw new InvalidArgumentException('El árbol debe ser un objeto JSON.');
        }

        return $this->validate($tree);
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return array<string, mixed>
     */
    public function validate(array $tree): array
    {
        $this->variants($tree['greeting']['variants'] ?? null, 'greeting.variants');
        $this->variants($tree['fallback']['variants'] ?? null, 'fallback.variants');
        $this->validateConnectors($tree['connectors'] ?? null);
        $this->validateSocial($tree['social'] ?? null);

        if (! is_array($tree['topics'] ?? null) || $tree['topics'] === []) {
            throw new InvalidArgumentException('El árbol debe incluir al menos un tema.');
        }

        $ids = [];
        foreach ($tree['topics'] as $index => $topic) {
            if (! is_array($topic) || ! is_string($topic['id'] ?? null) || preg_match('/^[a-z0-9-]{2,80}$/', $topic['id']) !== 1) {
                throw new InvalidArgumentException("topics.{$index}.id debe usar minúsculas, números o guiones.");
            }
            if (isset($ids[$topic['id']])) {
                throw new InvalidArgumentException("El tema {$topic['id']} está repetido.");
            }
            $ids[$topic['id']] = true;

            if (! is_string($topic['title'] ?? null) || trim($topic['title']) === '') {
                throw new InvalidArgumentException("topics.{$index}.title es obligatorio.");
            }
            if (! is_string($topic['description'] ?? null) || trim($topic['description']) === '') {
                throw new InvalidArgumentException("topics.{$index}.description es obligatorio.");
            }
            if (! is_array($topic['examples'] ?? null) || $topic['examples'] === []) {
                throw new InvalidArgumentException("topics.{$index}.examples debe incluir frases de ejemplo.");
            }
            foreach ($topic['examples'] as $example) {
                if (! is_string($example) || trim($example) === '' || mb_strlen($example) > 300) {
                    throw new InvalidArgumentException("topics.{$index}.examples contiene un valor inválido.");
                }
            }
            if (! is_array($topic['keywords'] ?? null) || $topic['keywords'] === []) {
                throw new InvalidArgumentException("topics.{$index}.keywords debe incluir palabras de referencia.");
            }
            foreach ($topic['keywords'] as $keyword) {
                if (! is_string($keyword) || trim($keyword) === '') {
                    throw new InvalidArgumentException("topics.{$index}.keywords contiene un valor inválido.");
                }
            }

            foreach (['summary', 'detail', 'next'] as $stage) {
                $this->variants($topic[$stage]['variants'] ?? null, "topics.{$index}.{$stage}.variants");
            }
        }

        return $tree;
    }

    private function variants(mixed $variants, string $path): void
    {
        if (! is_array($variants) || $variants === []) {
            throw new InvalidArgumentException("{$path} debe incluir al menos una variante.");
        }

        foreach ($variants as $variant) {
            if (! is_string($variant) || trim($variant) === '' || mb_strlen($variant) > 900) {
                throw new InvalidArgumentException("{$path} contiene una respuesta inválida.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return array<string, string>
     */
    public function lines(array $tree): array
    {
        $lines = [];
        foreach (['greeting', 'fallback'] as $section) {
            foreach ($tree[$section]['variants'] as $index => $text) {
                $lines["{$section}.{$index}"] = $text;
            }
        }
        foreach ($this->connectorFamilies($tree) as $family => $variants) {
            foreach ($variants as $index => $text) {
                $lines["connector.{$family}.{$index}"] = $text;
            }
        }
        foreach ($this->socialFamilies($tree) as $intent => $social) {
            foreach ($social['variants'] as $index => $text) {
                $lines["social.{$intent}.{$index}"] = $text;
            }
        }
        foreach ($tree['topics'] as $topic) {
            foreach (['summary', 'detail', 'next'] as $stage) {
                foreach ($topic[$stage]['variants'] as $index => $text) {
                    $lines["topic.{$topic['id']}.{$stage}.{$index}"] = $text;
                }
            }
        }

        return $lines;
    }

    /**
     * @return array<string, list<string>>
     */
    public function connectorFamilies(array $tree): array
    {
        $connectors = $tree['connectors'];
        if (array_is_list($connectors)) {
            return array_fill_keys(['queue', 'multi_intro', 'multi_bridge', 'multi_outro', 'continue_last'], $connectors);
        }

        return collect($connectors)
            ->map(fn (array $connector): array => $connector['variants'])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return array<string, array{description: string, examples: list<string>, keywords: list<string>, variants: list<string>}>
     */
    public function socialFamilies(array $tree): array
    {
        if (! is_array($tree['social'] ?? null)) {
            return [];
        }

        return $tree['social'];
    }

    private function validateConnectors(mixed $connectors): void
    {
        if (! is_array($connectors)) {
            throw new InvalidArgumentException('connectors debe incluir familias de variantes.');
        }

        if (array_is_list($connectors)) {
            $this->variants($connectors, 'connectors');

            return;
        }

        foreach (['queue', 'multi_intro', 'multi_bridge', 'multi_outro', 'continue_last'] as $family) {
            $this->variants($connectors[$family]['variants'] ?? null, "connectors.{$family}.variants");
        }
    }

    private function validateSocial(mixed $social): void
    {
        if ($social === null) {
            return;
        }

        if (! is_array($social) || array_is_list($social)) {
            throw new InvalidArgumentException('social debe incluir intenciones identificadas por nombre.');
        }

        foreach ($social as $intent => $definition) {
            if (! is_string($intent) || preg_match('/^[a-z0-9-]{2,80}$/', $intent) !== 1 || ! is_array($definition)) {
                throw new InvalidArgumentException('social contiene una intención inválida.');
            }
            if (! is_string($definition['description'] ?? null) || trim($definition['description']) === '') {
                throw new InvalidArgumentException("social.{$intent}.description es obligatorio.");
            }
            if (! is_array($definition['examples'] ?? null) || $definition['examples'] === []) {
                throw new InvalidArgumentException("social.{$intent}.examples debe incluir frases de ejemplo.");
            }
            foreach ($definition['examples'] as $example) {
                if (! is_string($example) || trim($example) === '' || mb_strlen($example) > 300) {
                    throw new InvalidArgumentException("social.{$intent}.examples contiene un valor inválido.");
                }
            }
            if (! is_array($definition['keywords'] ?? null) || $definition['keywords'] === []) {
                throw new InvalidArgumentException("social.{$intent}.keywords debe incluir palabras de referencia.");
            }
            foreach ($definition['keywords'] as $keyword) {
                if (! is_string($keyword) || trim($keyword) === '') {
                    throw new InvalidArgumentException("social.{$intent}.keywords contiene un valor inválido.");
                }
            }
            $this->variants($definition['variants'] ?? null, "social.{$intent}.variants");
        }
    }
}
