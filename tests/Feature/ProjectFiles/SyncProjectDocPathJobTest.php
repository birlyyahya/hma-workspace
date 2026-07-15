<?php

use App\Jobs\SyncProjectDocPathJob;
use App\Services\ProjectWriter;
use Illuminate\Support\Facades\Http;

test('it patches the BEPM document path to the new key', function () {
    Http::fake([
        '*admin-docs/*' => Http::response(['status' => 200], 200),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);

    (new SyncProjectDocPathJob(5, 1, 'projects_docs/2026/5/Arsip/x.pdf'))->handle(app(ProjectWriter::class));

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), 'admin-docs/1')
        && $request['file'] === 'projects_docs/2026/5/Arsip/x.pdf');
});

test('it throws so the queue retries when the BEPM update fails', function () {
    Http::fake(['*' => Http::response(['status' => 500], 500)]);

    expect(fn () => (new SyncProjectDocPathJob(5, 1, 'projects_docs/2026/5/Arsip/x.pdf'))->handle(app(ProjectWriter::class)))
        ->toThrow(RuntimeException::class);
});
