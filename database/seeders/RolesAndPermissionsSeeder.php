<?php
// database/seeders/RolesAndPermissionsSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perms = [
            // Бронювання
            'booking.view','booking.create','booking.update','booking.delete',
            'booking.export','booking.import','booking.manifest','booking.send_ticket',
            'booking.refund','booking.mark_paid',

            // Довідники/ціни/промокоди
            'price.manage','promo.manage','currency.manage','route.manage','bus.manage','user.manage',

            // Звіти
            'report.view','report.export',

            // Сканування
            'driver.scan',
        ];
        foreach ($perms as $p) Permission::firstOrCreate(['name'=>$p]);

        // Ролі
        $admin      = Role::firstOrCreate(['name'=>'admin']);
        $manager    = Role::firstOrCreate(['name'=>'manager']);
        $driver     = Role::firstOrCreate(['name'=>'driver']);
        $accountant = Role::firstOrCreate(['name'=>'accountant']);

        // Призначення прав
        $admin->givePermissionTo(Permission::all());

        $manager->givePermissionTo([
            'booking.view','booking.create','booking.update','booking.delete',
            'booking.export','booking.import','booking.manifest','booking.send_ticket',
            'price.manage','promo.manage','report.view','report.export',
        ]);

        $driver->givePermissionTo(['driver.scan','booking.view','booking.manifest']);

        $accountant->givePermissionTo(['report.view','report.export','booking.view','booking.mark_paid','booking.refund']);

        // опційно — зробити вашого користувача адміном:
        \App\Models\User::first()?->assignRole('admin');
    }
}
