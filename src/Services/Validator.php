<?php
declare(strict_types=1);

namespace App\Services;

class Validator
{
    private array $errors = [];
    private array $data = [];

    private function __construct() {}

    public static function make(array $data = []): self
    {
        $v = new self();
        $v->data = $data;
        return $v;
    }

    public function required(string $field, ?string $message = null): self
    {
        if (empty($this->data[$field] ?? null)) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' est obligatoire.';
        }
        return $this;
    }

    public function minLength(string $field, int $min, ?string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        if (strlen(trim($value)) < $min) {
            $this->errors[$field] = $message ?? ucfirst($field) . " doit contenir au moins {$min} caractères.";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, ?string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        if (strlen(trim($value)) > $max) {
            $this->errors[$field] = $message ?? ucfirst($field) . " ne peut pas dépasser {$max} caractères.";
        }
        return $this;
    }

    public function email(string $field, ?string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' doit être une adresse email valide.';
        }
        return $this;
    }

    public function unique(string $field, callable $checkFn, ?string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        if (!empty($value) && !$checkFn($value)) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' est déjà utilisé.';
        }
        return $this;
    }

    public function custom(string $field, callable $fn, ?string $message = null): self
    {
        if (!$fn($this->data[$field] ?? null)) {
            $this->errors[$field] = $message ?? "Validation échouée pour " . $field . '.';
        }
        return $this;
    }

    public function in(string $field, array $allowedValues, ?string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        if (!in_array($value, $allowedValues, true)) {
            $this->errors[$field] = $message ?? ucfirst($field) . " n'est pas une valeur autorisée.";
        }
        return $this;
    }

    /**
     * Valide qu'un champ HTML (ex: issu de l'éditeur Quill) n'est pas vide.
     * Considère '<p><br></p>', '<p></p>' et '' comme des valeurs vides.
     */
    public function notEmptyHtml(string $field, ?string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        $emptyValues = ['', '<p><br></p>', '<p></p>', '<p> </p>'];
        if (in_array(trim((string)$value), $emptyValues, true)) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' est obligatoire.';
        }
        return $this;
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return array_values($this->errors)[0] ?? null;
    }

    public function getCleanData(array $allowedFields = []): array
    {
        if (empty($allowedFields)) {
            return array_map(fn($v) => trim((string)$v), $this->data);
        }
        $clean = [];
        foreach ($allowedFields as $field) {
            $clean[$field] = isset($this->data[$field]) ? trim((string)$this->data[$field]) : '';
        }
        return $clean;
    }
}
