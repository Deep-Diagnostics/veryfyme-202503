<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Illuminate\Cache\CacheManager::class]->forget('roles');
        app()[\Illuminate\Cache\CacheManager::class]->forget('permissions');
        
        // Create default permissions
        $permissions = [
            // User management permissions
            ['name' => 'List users', 'slug' => 'users.list', 'description' => 'Can view list of users'],
            ['name' => 'View users', 'slug' => 'users.view', 'description' => 'Can view user details'],
            ['name' => 'Create users', 'slug' => 'users.create', 'description' => 'Can create new users'],
            ['name' => 'Edit users', 'slug' => 'users.edit', 'description' => 'Can edit existing users'],
            ['name' => 'Delete users', 'slug' => 'users.delete', 'description' => 'Can delete users'],
            
            // Role management permissions
            ['name' => 'List roles', 'slug' => 'roles.list', 'description' => 'Can view list of roles'],
            ['name' => 'View roles', 'slug' => 'roles.view', 'description' => 'Can view role details'],
            ['name' => 'Create roles', 'slug' => 'roles.create', 'description' => 'Can create new roles'],
            ['name' => 'Edit roles', 'slug' => 'roles.edit', 'description' => 'Can edit existing roles'],
            ['name' => 'Delete roles', 'slug' => 'roles.delete', 'description' => 'Can delete roles'],
            
            // Permission management permissions
            ['name' => 'List permissions', 'slug' => 'permissions.list', 'description' => 'Can view list of permissions'],
            ['name' => 'View permissions', 'slug' => 'permissions.view', 'description' => 'Can view permission details'],
            ['name' => 'Create permissions', 'slug' => 'permissions.create', 'description' => 'Can create new permissions'],
            ['name' => 'Edit permissions', 'slug' => 'permissions.edit', 'description' => 'Can edit existing permissions'],
            ['name' => 'Delete permissions', 'slug' => 'permissions.delete', 'description' => 'Can delete permissions'],
            
            // Dashboard access permission
            ['name' => 'Access Dashboard', 'slug' => 'panel_access.dashboard', 'description' => 'Can access the admin dashboard'],
            
            // Panel switch permission
            ['name' => 'Switch Panels', 'slug' => 'panels.switch', 'description' => 'Can switch between admin and application panels'],
        ];
        
        foreach ($permissions as $permission) {
            Permission::create($permission);
        }
        
        // Create roles and assign permissions
        
        // Super Admin role - has all permissions
        $superAdminRole = Role::create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'description' => 'Has access to everything',
        ]);
        
        // Admin role - has most permissions except some critical ones
        $adminRole = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Has access to most features',
        ]);
        
        // Editor role - can manage content but not users/permissions
        $editorRole = Role::create([
            'name' => 'Editor',
            'slug' => 'editor',
            'description' => 'Can edit content but not admin features',
        ]);
        
        // User role - basic user permissions
        $userRole = Role::create([
            'name' => 'User',
            'slug' => 'user',
            'description' => 'Regular user with limited permissions',
        ]);
        
        // Assign all permissions to super admin
        $allPermissions = Permission::all();
        $superAdminRole->permissions()->attach($allPermissions);
        
        // Assign selected permissions to admin
        $adminPermissions = Permission::whereIn('slug', [
            'users.list', 'users.view', 'users.create', 'users.edit',
            'roles.list', 'roles.view',
        ])->get();
        $adminRole->permissions()->attach($adminPermissions);
        
        // Create a default super admin user
        $superAdminUser = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
        ]);
        
        // Assign the super admin role to this user
        $superAdminUser->assignRole($superAdminRole);
        
        // Create a regular user
        $regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);
        
        // Assign the user role
        $regularUser->assignRole($userRole);
    }
}
