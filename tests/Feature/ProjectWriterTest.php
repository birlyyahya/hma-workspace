<?php

use App\Services\ProjectCache;
use App\Services\ProjectWriter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function makeProjectWriter(): ProjectWriter
{
    return new ProjectWriter('http://api.test', new ProjectCache('http://api.test'));
}

test('createProject succeeds only when body status is 201', function () {
    Http::fake(['*/projects' => Http::response(['status' => 201, 'data' => ['id' => 9]], 200)]);

    $result = makeProjectWriter()->createProject(['name' => 'X']);

    expect($result['ok'])->toBeTrue()
        ->and($result['body']['data']['id'])->toBe(9)
        ->and($result['status'])->toBe(200)
        ->and($result['error'])->toBeNull();
});

test('createProject fails when body status is not 201', function () {
    Http::fake(['*/projects' => Http::response(['status' => 422, 'errors' => ['name' => ['required']]], 200)]);

    expect(makeProjectWriter()->createProject([])['ok'])->toBeFalse();
});

test('updateProject succeeds on body status 200 (HTTP 200 masking)', function () {
    Http::fake(['*/projects/5' => Http::response(['status' => 200], 200)]);

    $result = makeProjectWriter()->updateProject(5, ['name' => 'Y']);

    expect($result['ok'])->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'PATCH' && str_ends_with($request->url(), '/projects/5'));
});

test('deleteProject succeeds on HTTP 2xx', function () {
    Http::fake(['*/projects/3' => Http::response([], 204)]);

    expect(makeProjectWriter()->deleteProject(3)['ok'])->toBeTrue();
});

test('createTimeline requires body status 201 and deleteTimeline requires 200', function () {
    Http::fake([
        '*/timelines' => Http::response(['status' => 201, 'data' => ['id' => 1]], 200),
        '*/timelines/1' => Http::response(['status' => 200], 200),
    ]);

    $writer = makeProjectWriter();

    expect($writer->createTimeline(['title' => 't'])['ok'])->toBeTrue()
        ->and($writer->deleteTimeline(1)['ok'])->toBeTrue();
});

test('createTeam and deleteTeam follow BEPM body status codes', function () {
    Http::fake([
        '*/project-teams' => Http::response(['status' => 201, 'data' => ['user_id' => 42]], 200),
        '*/project-teams/7' => Http::response(['status' => 200], 200),
    ]);

    $writer = makeProjectWriter();

    expect($writer->createTeam(1, 42)['ok'])->toBeTrue()
        ->and($writer->deleteTeam(7, 42)['ok'])->toBeTrue();
});

test('createCompany posts multipart and succeeds on HTTP 2xx', function () {
    Http::fake(['*/companies' => Http::response(['data' => ['id' => 2]], 201)]);

    $result = makeProjectWriter()->createCompany(['name' => 'PT A'], ['contents' => 'BIN', 'name' => 'ttd.png']);

    expect($result['ok'])->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/companies') && $request->isMultipart());
});

test('deleteSpectechCategory succeeds on HTTP 2xx', function () {
    Http::fake(['*/activity-categories/11' => Http::response([], 200)]);

    expect(makeProjectWriter()->deleteSpectechCategory(11, 100)['ok'])->toBeTrue();
});

test('uploadDoc succeeds only when body status is 201', function () {
    Http::fake(['*/admin-docs' => Http::response(['status' => 201], 200)]);

    expect(makeProjectWriter()->uploadDoc(['title' => 'doc'])['ok'])->toBeTrue();
});

test('a transport failure returns ok=false with the error message, never throwing', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $result = makeProjectWriter()->deleteProject(1);

    expect($result['ok'])->toBeFalse()
        ->and($result['status'])->toBeNull()
        ->and($result['error'])->toContain('Connection timed out');
});
