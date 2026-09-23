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
        $this->variants($tree['connectors'] ?? null, 'connectors');

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
        foreach ($tree['connectors'] as $index => $text) {
            $lines["connector.{$index}"] = $text;
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
}
