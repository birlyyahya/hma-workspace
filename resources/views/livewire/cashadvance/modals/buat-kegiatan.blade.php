<?php

use Livewire\Volt\Component;

new class extends Component {
    public string $kodeCa = '';

    public $rab;
    public $dana_cair;

    public function mount(string $kodeCa = ''): void
    {
        $this->kodeCa = $kodeCa;
    }
}; ?>

<flux:modal name="buat-kegiatan" class="w-lg">
    <form class="space-y-6">
        <div>
            <flux:heading size="lg">Buat Dompet Kegiatan</flux:heading>
            <flux:text class="mt-1">Dana cair dicatat sebagai transaksi masuk pertama.</flux:text>
        </div>

        <flux:input label="Nama Kegiatan" placeholder="mis. Survei Lokasi Bandung" />
        <flux:field>
            <flux:label>RAB (Opsional)</flux:label>
            <x-rupiah-input model="rab" placeholder="8.000.000.000" />
            <flux:error name="rab" />
        </flux:field>

        <flux:field>
            <flux:label>Dana Cair</flux:label>
            <x-rupiah-input model="dana_cair" placeholder="8.000.000.000" />
            <flux:error name="dana_cair" />
        </flux:field>
        <flux:input type="file" label="Bukti pencairan (wajib &mdash; jpg, png, pdf)" />

        <flux:button type="submit" variant="primary" class="w-full">Buat Dompet</flux:button>
    </form>
</flux:modal>
