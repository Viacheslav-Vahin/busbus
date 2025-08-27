<?php
// database/seeders/RolesSeeder.php
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder {
    public function run() { Role::findOrCreate('passenger'); }
}
