<?php

namespace App\Livewire\Forms;

use App\Services\ProjectWriter;
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

    protected function payload(?int $projectId = null): array
    {
        $payload = [
            'name' => $this->name,
            'qty_total' => $this->quantity,
            'total_nominal' => (int) preg_replace('/[^0-9]/', '', (string) $this->price),
            'note' => $this->notes,
            'type' => $this->type,
        ];

        if ($projectId !== null) {
            $payload['project_id'] = $projectId;
        }

        if ($this->idUpdate !== null) {
            $payload['qty_recived'] = (int) ($this->received_quantity ?? 0);
        }

        return $payload;
    }

    /**
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function store(int $projectId): array
    {
        $this->validate();

        return app(ProjectWriter::class)->createSpectechCategory($projectId, $this->payload($projectId));
    }

    public function setUpdate(array $spectech): void
    {
        $this->name = $spectech['name'] ?? '';
        $this->quantity = (int) ($spectech['qty_total'] ?? 0);
        $this->price = (int) ($spectech['total_nominal'] ?? 0);
        $this->received_quantity = (int) ($spectech['qty_recived'] ?? 0);

        $this->notes = $spectech['note'] ?? null;

        $this->type = $spectech['type'] ?? 'hardware';
        $this->idUpdate = (int) $spectech['id'];
    }

    /**
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function update(int $projectId): array
    {
        $this->validate();

        return app(ProjectWriter::class)->updateSpectechCategory((int) $this->idUpdate, $projectId, $this->payload());
    }
}
