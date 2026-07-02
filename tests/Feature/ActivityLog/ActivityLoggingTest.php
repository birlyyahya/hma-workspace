<?php

use App\Models\Role;
use App\Models\SupportArticle;
use App\Models\User;
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

    expect($activity?->description)->toBe('Mengajukan izin baru');
});

test('login and logout events are recorded under the auth log', function () {
    $user = User::factory()->create();

    event(new Login('web', $user, false));
    event(new Logout('web', $user));

    $logs = Activity::query()->where('log_name', 'auth')->orderBy('id')->pluck('description');

    expect($logs->all())->toBe(['Login', 'Logout']);
});
