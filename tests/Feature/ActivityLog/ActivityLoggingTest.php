<?php

use App\Models\Role;
use App\Models\SupportArticle;
use App\Models\User;
use App\Services\DarCache;
use App\Services\DarWriter;
use App\Services\IzinCache;
use App\Services\IzinWriter;
use App\Services\ProjectCache;
use App\Services\ProjectWriter;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

test('creating a role records an activity under the role log', function () {
    $role = Role::factory()->create(['name' => 'Auditor']);

    $activity = Activity::query()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('role')
        ->and($activity->description)->toBe('Role dibuat')
        ->and($activity->subject_id)->toBe($role->id);
});

test('updating a model only logs the dirty attributes', function () {
    $article = SupportArticle::create([
        'user_id' => User::factory()->create()->id,
        'title' => 'Judul Awal',
        'content' => 'Isi',
    ]);

    $article->update(['title' => 'Judul Baru']);

    $activity = Activity::query()->where('log_name', 'knowledge')->latest('id')->first();

    expect($activity->description)->toBe('Artikel diperbarui')
        ->and($activity->attribute_changes->get('attributes'))->toHaveKey('title')
        ->and($activity->attribute_changes->get('attributes')['title'])->toBe('Judul Baru');
});

test('the user model never logs sensitive attributes', function () {
    $user = User::factory()->create();

    $user->update(['password' => 'new-secret-password']);

    $activity = Activity::query()->where('log_name', 'user')->latest('id')->first();

    // password diperbarui tetapi tidak boleh muncul di properties
    $changes = $activity?->properties->get('attributes') ?? [];
    expect($changes)->not->toHaveKey('password');
});

test('a successful project write is logged with the acting user as causer', function () {
    Http::fake(['*/projects' => Http::response(['status' => 201, 'data' => ['id' => 9]], 200)]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $writer = new ProjectWriter('http://api.test', new ProjectCache('http://api.test'));
    $writer->createProject(['name' => 'Proyek A']);

    $activity = Activity::query()->where('log_name', 'project')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe('created')
        ->and($activity->description)->toBe('Membuat project baru')
        ->and($activity->causer_id)->toBe($user->id);
});

test('deleting a project document is logged', function () {
    Http::fake(['*/admin-docs/7' => Http::response(['status' => 200], 200)]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $result = (new ProjectWriter('http://api.test', new ProjectCache('http://api.test')))->deleteDoc(7);

    $activity = Activity::query()->where('log_name', 'project')->latest('id')->first();

    expect($result['ok'])->toBeTrue()
        ->and($activity->event)->toBe('deleted')
        ->and($activity->description)->toBe('Menghapus dokumen admin project #7')
        ->and($activity->causer_id)->toBe($user->id);
});

test('a failed external write is not logged', function () {
    Http::fake(['*/projects' => Http::response(['status' => 422], 200)]);

    (new ProjectWriter('http://api.test', new ProjectCache('http://api.test')))
        ->createProject([]);

    expect(Activity::query()->where('log_name', 'project')->exists())->toBeFalse();
});

test('izin creation is logged when the API reports success', function () {
    Http::fake(['*/global/izin/create-izin-saya' => Http::response(['success' => true], 200)]);

    (new IzinWriter('http://api.test', new IzinCache('http://api.test')))
        ->createIzin(['jenis_izin' => 'cuti']);

    $activity = Activity::query()->where('log_name', 'izin')->latest('id')->first();

    expect($activity?->description)->toBe('Mengajukan izin baru')
        ->and($activity->properties->get('payload'))->toBe(['jenis_izin' => 'cuti']);
});

test('creating a dar activity is logged with the request payload in properties', function () {
    Http::fake(['*/global/dar/create' => Http::response(['success' => true], 200)]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $payload = ['user_id' => $user->id, 'activity' => 'Survey lokasi', 'status' => 1];

    $result = (new DarWriter('http://api.test', new DarCache('http://api.test')))
        ->createActivity($payload);

    $activity = Activity::query()->where('log_name', 'dar')->latest('id')->first();

    expect($result['ok'])->toBeTrue()
        ->and($activity)->not->toBeNull()
        ->and($activity->event)->toBe('created')
        ->and($activity->description)->toBe('Membuat aktivitas DAR baru')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties->get('payload'))->toBe($payload);
});

test('updating a dar activity records the payload in properties', function () {
    Http::fake(['*/global/dar/update/3' => Http::response(['success' => true], 200)]);

    (new DarWriter('http://api.test', new DarCache('http://api.test')))
        ->updateActivity(3, ['activity' => 'Revisi laporan']);

    $activity = Activity::query()->where('log_name', 'dar')->latest('id')->first();

    expect($activity?->properties->get('id'))->toBe(3)
        ->and($activity->properties->get('payload'))->toBe(['activity' => 'Revisi laporan']);
});

test('a project write records the payload in properties', function () {
    Http::fake(['*/projects' => Http::response(['status' => 201], 200)]);

    (new ProjectWriter('http://api.test', new ProjectCache('http://api.test')))
        ->createProject(['name' => 'Proyek B', 'company_id' => 4]);

    $activity = Activity::query()->where('log_name', 'project')->latest('id')->first();

    expect($activity?->properties->get('payload'))->toBe(['name' => 'Proyek B', 'company_id' => 4]);
});

test('spectech creation and update are logged with the payload', function () {
    Http::fake([
        '*/spekteks/9' => Http::response(['status' => 200], 200),
        '*/spekteks' => Http::response(['status' => 201], 201),
    ]);

    $writer = new ProjectWriter('http://api.test', new ProjectCache('http://api.test'));

    $writer->createSpectechCategory(7, ['name' => 'Router', 'qty_total' => 2, 'project_id' => 7]);
    $writer->updateSpectechCategory(9, 7, ['name' => 'Router Baru', 'qty_total' => 3]);

    $logs = Activity::query()->where('log_name', 'project')->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->event)->toBe('created')
        ->and($logs[0]->description)->toBe('Menambah spektek project #7')
        ->and($logs[0]->properties->get('payload'))->toBe(['name' => 'Router', 'qty_total' => 2, 'project_id' => 7])
        ->and($logs[1]->event)->toBe('updated')
        ->and($logs[1]->description)->toBe('Memperbarui spektek #9 (project #7)')
        ->and($logs[1]->properties->get('payload'))->toBe(['name' => 'Router Baru', 'qty_total' => 3]);
});

test('login and logout events are recorded under the auth log', function () {
    $user = User::factory()->create();

    event(new Login('web', $user, false));
    event(new Logout('web', $user));

    $logs = Activity::query()->where('log_name', 'auth')->orderBy('id')->pluck('description');

    expect($logs->all())->toBe(['Login', 'Logout']);
});
