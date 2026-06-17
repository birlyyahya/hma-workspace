<?php

namespace App\Livewire\Forms;

use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Validate;
use Livewire\Form;

class SpectechForm extends Form
{
    #[Validate('required|string|min:3|max:120')]
    public string $name = '';

    #[Validate('required|integer|min:1')]
    public ?int $quantity = null;

    #[Validate('required')]
    public ?string $price = null;

    #[Validate('nullable|string|max:500')]
    public ?string $notes = null;

    #[Validate('nullable|integer|min:0|lte:quantity')]
    public ?int $received_quantity = 0;

    #[Validate('required|in:hardware,software')]
    public string $type = 'hardware';

    public ?int $idUpdate = null;

    protected function endpoint(?string $suffix = null): string
    {
        return rtrim((string) config('services.api_project'), '/') . '/activity-categories' . ($suffix ? '/' . $suffix : '');
    }

    protected function payload(?int $projectId = null): array
    {
        $payload = [
            'name' => $this->name,
            'qty_total' => $this->quantity,
            'total_nominal' => (int) preg_replace('/[^0-9]/', '', (string) $this->price),
            'type' => $this->type,
        ];

        if (filled($this->notes)) {
            $payload['note'] = $this->notes;
        }

        if ($projectId !== null) {
            $payload['project_id'] = $projectId;
        }

        if ($this->idUpdate !== null) {
            $payload['qty_recived'] = (int) ($this->received_quantity ?? 0);
        }

        return $payload;
    }

    public function store(int $projectId): \Illuminate\Http\Client\Response
    {
        $this->validate();

        return Http::post($this->endpoint(), $this->payload($projectId));
    }

    public function setUpdate(array $spectech): void
    {
        $this->name = $spectech['name'] ?? '';
        $this->quantity = (int) ($spectech['qty_total'] ?? 0);
        $this->price = number_format((float) ($spectech['total_nominal'] ?? 0), 0, ',', '.');
        $this->received_quantity = (int) ($spectech['qty_recived'] ?? 0);

        $this->notes = $spectech['note'] ?? null;

        $this->type = $spectech['type'] ?? 'hardware';
        $this->idUpdate = (int) $spectech['id'];
    }

    public function update(): \Illuminate\Http\Client\Response
    {
        $this->validate();

        return Http::post($this->endpoint((string) $this->idUpdate), $this->payload());
    }
}
