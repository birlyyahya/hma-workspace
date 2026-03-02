<?php

namespace Database\Seeders;

use App\Models\Role;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
   public function run(): void
    {
        User::factory(1)->create();

        // Bulk insert from API Project
//         $response = Http::withToken(env('TOKEN_KEY'))->get(env('API_PROJECT') . '/users?limit=100')->json();

//         if (!$response['status']) {
//             throw new \Exception('Gagal ambil data user dari API');
//         }

//         $users = $response['data'];

//         /*
// |--------------------------------------------------------------------------
// | Mapping Username → Role Slug
// |--------------------------------------------------------------------------
// */

//         $usernameRoleMap = [
//             'itandre'  => 'manager',
//             'itsamuel' => 'asmen',
//             'itagus'   => 'spv-it-software',
//         ];

//         /*
// |--------------------------------------------------------------------------
// | Default Role Jika Tidak Ada Mapping
// |--------------------------------------------------------------------------
// */

//         $defaultRoleId = Role::where('slug', 'staff-it-software')->value('id');

//         foreach ($users as $user) {

//             $roleSlug = $usernameRoleMap[$user['username']] ?? null;

//             $roleId = $roleSlug
//                 ? Role::where('slug', $roleSlug)->value('id')
//                 : $defaultRoleId;

//             User::updateOrCreate(
//                 [
//                     'username' => $user['username'],
//                 ],
//                 [
//                     'id'       => $user['id'],
//                     'name'     => $user['name'],
//                     'email'    => $user['name'] . '@gmail.com',
//                     'password' => bcrypt('123'),
//                     'role_id'  => $roleId,
//                 ]
//             );
//         }

//         /*
// |--------------------------------------------------------------------------
// | Buat Akun General MAP & HRD
// |--------------------------------------------------------------------------
// */

//         $mapRoleId = Role::where('slug', 'map')->value('id');
//         $hrdRoleId = Role::where('slug', 'hrd')->value('id');

//         User::updateOrCreate(
//             ['username' => 'map'],
//             [
//                 'name'     => 'MAP',
//                 'email'    => 'map@company.com',
//                 'password' => bcrypt('123'),
//                 'role_id'  => $mapRoleId,
//             ]
//         );

//         User::updateOrCreate(
//             ['username' => 'hrd'],
//             [
//                 'name'     => 'HRD',
//                 'email'    => 'hrd@company.com',
//                 'password' => bcrypt('123'),
//                 'role_id'  => $hrdRoleId,
//             ]
//         );



        // Import Role dari factory roles
        // Role::factory()->superAdmin()->create();
        // Role::factory()->gm()->create();
        // Role::factory()->manager()->create();
        // Role::factory()->asmen()->create();
        // Role::factory()->spvInfra()->create();
        // Role::factory()->spvSoftware()->create();
        // Role::factory()->staff('it-software')->create();
        // Role::factory()->viewer('hrd')->create();
        // Role::factory()->viewer('map')->create();

    }
}
