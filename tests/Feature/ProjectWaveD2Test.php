<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

beforeEach(fn () => $this->withViewErrors([]));

function fakeCompany(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'name' => 'PT Alpha',
        'address' => 'Jl. Mawar 1',
        'director_name' => 'Budi',
        'established_date' => '2020-01-01',
        'letter_head' => 'companies/ttd.png',
    ], $overrides);
}

test('perusahaan fetchCompanies populates companies and pagination via ProjectCache', function () {
    Http::fake([
        '*companies/search*' => Http::response(['data' => [fakeCompany()], 'pagination' => ['total' => 1]], 200),
    ]);
    $this->actingAs(User::factory()->create());

    Volt::test('project.perusahaan')
        ->call('fetchCompanies')
        ->assertSet('companies', [fakeCompany()])
        ->assertSet('pagination', ['total' => 1]);
});

test('perusahaan delete sends the DELETE through ProjectWriter', function () {
    Http::fake([
        '*companies/search*' => Http::response(['data' => [], 'pagination' => []], 200),
        '*companies/9' => Http::response([], 200),
    ]);
    $this->actingAs(User::factory()->create());

    Volt::test('project.perusahaan')
        ->set('companies', [fakeCompany(['id' => 9])])
        ->set('deletingId', 9)
        ->call('delete');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/companies/9'));
});

test('perusahaan save posts a multipart create through ProjectWriter', function () {
    Http::fake([
        '*companies/search*' => Http::response(['data' => [], 'pagination' => []], 200),
        '*companies' => Http::response(['data' => ['id' => 2]], 201),
    ]);
    $this->actingAs(User::factory()->create());

    Volt::test('project.perusahaan')
        ->set('name', 'PT Baru')
        ->set('address', 'Jl. Melati 2')
        ->set('director_name', 'Siti')
        ->set('established_date', '2021-05-05')
        ->set('letter_head', UploadedFile::fake()->image('ttd.png'))
        ->call('save')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/companies')
        && $request->isMultipart());
});
