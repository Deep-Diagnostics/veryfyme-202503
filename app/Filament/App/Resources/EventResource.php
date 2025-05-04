<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\EventResource\Pages;
use App\Filament\App\Resources\EventResource\RelationManagers;
use App\Models\Event;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    
    protected static ?string $navigationGroup = 'Event Management';
    
    protected static ?int $navigationSort = 1;
    
    protected static ?string $recordTitleAttribute = 'event_name';

    public static function canAccess(): bool
    {
        return auth()->user()->hasPermission('events.view');
    }

    /**
     * Check if the current user can create new events
     */
    public static function canCreate(): bool
    {
        return auth()->user()->hasPermission('events.create');
    }

    /**
     * Check if the current user can edit a user
     */
    public static function canEdit(Model $record): bool
    {
        return auth()->user()->hasPermission('events.edit');
    }

    /**
     * Check if the current user can delete a user
     */
    public static function canDelete(Model $record): bool
    {
        return auth()->user()->hasPermission('events.delete');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Event Identifiers')->hidden()
                    ->schema([
                        Forms\Components\TextInput::make('public_workshop_id')
                            ->label('Public Workshop ID')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Auto-generated UUID for this event')
                            ->visible(fn ($record) => $record !== null),
                            
                        // Forms\Components\TextInput::make('public_key')
                        //     ->label('Public Key')
                        //     ->required()
                        //     ->maxLength(9)
                        //     ->unique(ignoreRecord: true)
                        //     ->helperText('9-character unique public identifier for the event'),
                            
                        Forms\Components\TextInput::make('public_numerical_key')
                            ->label('Numerical Key')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($record) => $record !== null),
                    ]),
                    
                Forms\Components\Section::make('Event Details')->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('event_name')->columnSpanFull()
                            ->label('Event Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter event name')
                            ->columnSpanFull()->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('online_link', Str::slug($state)))
                            ->afterStateHydrated(function ($state, callable $set) {
                                $set('online_link', Str::slug($state));
                                $set('event_name', ucfirst($state));
                            }),

                            
                        Forms\Components\TextInput::make('online_link')->columnSpanFull()
                            ->label('Online Meeting Slug')
                            ->maxLength(45)
                            ->url()
                            ->prefix('https://'.env('APP_URL').'events/')
                            ->placeholder('Enter online meeting slug')
                            ->columnSpanFull()
                            ->helperText('Enter the online meeting slug')
                            ->afterStateUpdated(fn ($state, callable $set) => $set('online_link', Str::slug($state))),
                            
                            DatePicker::make('event_start_dT')
                            ->label('Start Date & Time')
                            ->required()
                            ->seconds(false)
                            ->timezone('Africa/Lagos')
                            ,
                            
                            DatePicker::make('event_end_dT')
                            ->label('End Date & Time')
                            ->seconds(false)
                            ->timezone('Africa/Lagos')
                            ->after('event_start_dT')
                            ,

                        Select::make('event_type')->columnSpanFull()
                            ->label('Event Type')
                            ->options([
                                'workshop' => 'Workshop',
                                'seminar' => 'Seminar',
                                'conference' => 'Conference',
                                'webinar' => 'Webinar',
                                'training' => 'Training',
                                'other' => 'Other',
                            ])
                            ->searchable()
                            ->columnStart(2),
                            
                        Select::make('event_privacy')->columnSpanFull()
                            ->label('Privacy Setting')
                            ->options([
                                'public' => 'Public',
                                'private' => 'Private',
                                'restricted' => 'Restricted',
                            ])
                            ->default('public')
                            ->required()
                            ->helperText('Controls who can view and register for this event'),
                    ]),
                Forms\Components\Section::make('CPD Points')
                    ->schema([    
                        TextInput::make('cpd_points_earned')
                        ->label('CPD Points')
                        ->numeric()
                        ->default(1)
                        ->minValue(0)
                        ->maxValue(10000)
                        ->step(0.1)
                        ->columnStart(1),
                    ]),
                Forms\Components\Section::make('Content')
                    ->schema([ 
                        
                        
                        RichEditor::make('event_description')
                            ->label('Event Description')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('event-attachments')
                            ->columnSpanFull(),
                    ]),
                    
                Forms\Components\Section::make('Advanced Settings')
                    ->schema([
                        Forms\Components\KeyValue::make('misc_data')
                            ->label('Additional Metadata')
                            ->keyPlaceholder('Key')
                            ->valuePlaceholder('Value')
                            ->columnSpanFull(),
                            
                        Toggle::make('archived')
                            ->label('Archive Event')
                            ->default(false)
                            ->helperText('Archived events will not be shown in listings')
                            ->onColor('danger')
                            ->offColor('success'),
                    ])->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('public_key')
                    ->label('Event ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('event_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                    
                Tables\Columns\TextColumn::make('event_start_dT')
                    ->label('Start Date')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Type')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                    
                Tables\Columns\IconColumn::make('archived')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-archive-box')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
                    
                Tables\Columns\TextColumn::make('event_privacy')
                    ->label('Privacy')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success',
                        'private' => 'danger',
                        'restricted' => 'warning',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('cpd_points_earned')
                    ->label('Points')
                    ->numeric()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options([
                        'workshop' => 'Workshop',
                        'seminar' => 'Seminar',
                        'conference' => 'Conference',
                        'webinar' => 'Webinar',
                        'training' => 'Training',
                        'other' => 'Other',
                    ]),
                    
                SelectFilter::make('event_privacy')
                    ->label('Privacy')
                    ->options([
                        'public' => 'Public',
                        'private' => 'Private',
                        'restricted' => 'Restricted',
                    ]),
                    
                Filter::make('upcoming')
                    ->label('Upcoming Events')
                    ->query(fn (Builder $query): Builder => $query->where('event_start_dT', '>=', Carbon::now())),
                    
                Filter::make('past')
                    ->label('Past Events')
                    ->query(fn (Builder $query): Builder => $query->where('event_start_dT', '<', Carbon::now())),
                    
                Filter::make('archived')
                    ->label('Archived Only')
                    ->query(fn (Builder $query): Builder => $query->where('archived', true)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->action(fn (Event $record) => $record->update(['archived' => true]))
                    ->requiresConfirmation()
                    ->hidden(fn (Event $record): bool => $record->archived),
                Tables\Actions\Action::make('unarchive')
                    ->label('Unarchive')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->action(fn (Event $record) => $record->update(['archived' => false]))
                    ->requiresConfirmation()
                    ->visible(fn (Event $record): bool => $record->archived),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('archiveSelected')
                        ->label('Archive Selected')
                        ->icon('heroicon-o-archive-box')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['archived' => true])),
                    Tables\Actions\BulkAction::make('unarchiveSelected')
                        ->label('Unarchive Selected')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['archived' => false])),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
