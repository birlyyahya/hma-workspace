<?php

use App\Services\ProjectCache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('projectFor unwraps the first element of the data list', function () {
    Http::fake(['*/projects/12' => Http::response(['status' => 200, 'data' => [['id' => 12, 'name' => 'P']]], 200)]);

    expect((new ProjectCache('http://api.test'))->projectFor(12))->toBe(['id' => 12, 'name' => 'P']);
});

test('projectFor returns empty when connection fails, without throwing', function () {
    Http::fake(fn () => throw new ConnectionException('down'));

    expect((new ProjectCache('http://api.test'))->projectFor(99))->toBe([]);
});

test('searchCompanies returns the full payload (data + pagination)', function () {
    Http::fake(['*/companies/search*' => Http::response(['data' => [['id' => 1]], 'pagination' => ['total' => 1]], 200)]);

    $result = (new ProjectCache('http://api.test'))->searchCompanies(['name' => 'A']);

    expect($result['data'])->toBe([['id' => 1]])
        ->and($result['pagination'])->toBe(['total' => 1]);
});

test('searchDocs returns empty array when the API fails', function () {
    Http::fake(fn () => throw new ConnectionException('down'));

    expect((new ProjectCache('http://api.test'))->searchDocs(['project_id' => 1]))->toBe([]);
});

test('docCategories returns the data array', function () {
    Http::fake(['*/admin-doc-categories*' => Http::response(['data' => [['id' => 1, 'name' => 'Kontrak']]], 200)]);

    expect((new ProjectCache('http://api.test'))->docCategories())->toBe([['id' => 1, 'name' => 'Kontrak']]);
});
