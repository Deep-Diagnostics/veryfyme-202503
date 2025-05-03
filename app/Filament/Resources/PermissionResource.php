<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionResource\Pages;
use App\Filament\Resources\PermissionResource\RelationManagers;
use App\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification; // Add this import

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    
    protected static ?string $navigationGroup = 'Access Management';
    
    protected static ?int $navigationSort = 2;
    
    /**
     * Check if the current user has permission to access the PermissionResource
     */
    public static function canAccess(): bool
    {
        return auth()->user()->hasPermission('permissions.view');
    }
    
    /**
     * Check if the current user can create new permissions
     */
    public static function canCreate(): bool
    {
        return auth()->user()->hasPermission('permissions.create');
    }

    /**
     * Check if the current user can edit a permission
     */
    public static function canEdit(Model $record): bool
    {
        return auth()->user()->hasPermission('permissions.edit');
    }

    /**
     * Check if the current user can delete a permission
     */
    public static function canDelete(Model $record): bool
    {
        return auth()->user()->hasPermission('permissions.delete');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('The machine-readable name of the permission (e.g. create_posts, edit_users)'),
                        
                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->paginated([ 25, 50, 100, 250, 'all'])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('purple')  // Changed from 'success' to 'purple'
                    ->requiresConfirmation()
                    ->modalHeading('Duplicate Permission')
                    ->modalDescription('Create a new permission based on this one.')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->default(fn ($record) => "Copy of {$record->name}"),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->default(fn ($record) => "{$record->slug}_copy"),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->default(fn ($record) => $record->description),
                    ])
                    ->action(function (array $data, $record) {
                        Permission::create([
                            'name' => $data['name'],
                            'slug' => $data['slug'],
                            'description' => $data['description'],
                        ]);
                        
                        Filament\Notifications\Notification::make()
                            ->title('Permission duplicated successfully')
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => auth()->user()->hasPermission('permissions.create')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('assignToRoles')
                        ->label('Add to Role(s)')
                        ->icon('heroicon-o-user-group')
                        ->color('success')
                        ->form([
                            Forms\Components\Select::make('roleIds')
                                ->label('Select Roles')
                                ->multiple()
                                ->options(function () {
                                    $roleModel = app(config('permission.models.role', \App\Models\Role::class));
                                    return $roleModel::pluck('name', 'id')->toArray();
                                })
                                ->preload()
                                ->searchable()
                                ->required()
                                ->helperText('Selected permissions will be assigned to these roles'),
                        ])
                        ->action(function ($records, array $data) {
                            $roleIds = $data['roleIds']; // Changed from 'roles' to 'roleIds'
                            $permissionIds = $records->pluck('id')->toArray();
                            $rolesCount = count($roleIds);
                            $permissionsCount = count($permissionIds);
                            
                            // Get the Role model class
                            $roleModel = app(config('permission.models.role', \App\Models\Role::class));
                            
                            // Find the selected roles
                            $roles = $roleModel::whereIn('id', $roleIds)->get();
                            
                            // Attach permissions to each role
                            foreach ($roles as $role) {
                                $role->permissions()->syncWithoutDetaching($permissionIds);
                            }
                            
                            Notification::make()
                                ->title($permissionsCount . ' permissions assigned to ' . $rolesCount . ' role(s)')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('Add Permissions to Role(s)')
                        ->modalDescription('The selected permissions will be added to the chosen role(s).')
                        ->modalSubmitActionLabel('Add Permissions')
                        ->visible(fn () => auth()->user()->hasPermission('roles.edit')),
                ]),
            ])
            ->headerActions([
              //  Tables\Actions\CreateAction::make(),
                Tables\Actions\Action::make('generateCrud')
                    ->label('Generate CRUD Permissions')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('resource')
                            ->label('Resource Name')
                            ->placeholder('e.g. users, posts, events')
                            ->required()
                            ->helperText('Enter the resource name in plural form (e.g. users, posts)'),
                    ])
                    ->action(function (array $data) {
                        $resource = strtolower(trim($data['resource']));
                        // Remove trailing 's' if present to get singular form for description
                        $singularResource = rtrim($resource, 's');
                        
                        $permissions = [
                            [
                                'name' => ucfirst($resource) . ' List',
                                'slug' => $resource . '.list',
                                'description' => 'Ability to view list of ' . $resource,
                            ],
                            [
                                'name' => ucfirst($resource) . ' View',
                                'slug' => $resource . '.view',
                                'description' => 'Ability to view ' . $singularResource . ' details',
                            ],
                            [
                                'name' => ucfirst($resource) . ' Create',
                                'slug' => $resource . '.create',
                                'description' => 'Ability to create new ' . $singularResource,
                            ],
                            [
                                'name' => ucfirst($resource) . ' Edit',
                                'slug' => $resource . '.edit',
                                'description' => 'Ability to edit ' . $singularResource,
                            ],
                            [
                                'name' => ucfirst($resource) . ' Delete',
                                'slug' => $resource . '.delete',
                                'description' => 'Ability to delete ' . $singularResource,
                            ],
                        ];
                        
                        $created = 0;
                        $skipped = 0;
                        
                        foreach ($permissions as $permission) {
                            // Check if permission already exists
                            if (!Permission::where('slug', $permission['slug'])->exists()) {
                                Permission::create($permission);
                                $created++;
                            } else {
                                $skipped++;
                            }
                        }
                        
                        Notification::make()
                            ->title($created . ' permissions created successfully')
                            ->body($skipped > 0 ? $skipped . ' permissions were skipped (already exist)' : '')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Generate CRUD Permissions')
                    ->modalDescription('This will create standard list, view, create, edit, and delete permissions for the specified resource.')
                    ->modalSubmitActionLabel('Generate Permissions')
                    ->visible(fn () => auth()->user()->hasPermission('permissions.create')),
            ]);
    }

    public static function getRelations(): array
    {
        return [
           RelationManagers\RolesRelationManager::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
