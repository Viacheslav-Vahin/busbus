<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;
use App\Filament\Resources\ActivityResource\Pages;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon   = 'heroicon-o-queue-list';
    protected static ?string $navigationLabel  = 'Журнал змін';
    protected static ?string $navigationGroup  = 'Сервіс';
    protected static ?int    $navigationSort   = 99;
    protected static ?string $slug             = 'activities';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Коли')->since()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('log_name')->label('Лог')->badge()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('event')->label('Подія')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('description')->label('Опис')->limit(80)->wrap()->toggleable(),
                Tables\Columns\TextColumn::make('causer_id')
                    ->label('Автор')
                    ->formatStateUsing(fn ($state, Activity $r) => $r->causer?->name ?? $state)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subject_type')->label('Обʼєкт')->toggleable(),
                Tables\Columns\TextColumn::make('subject_id')->label('ID')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')
                    ->label('Лог')
                    ->options(fn () => Activity::query()
                        ->select('log_name')->distinct()
                        ->pluck('log_name', 'log_name')->toArray()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Запис активності')
                    ->modalContent(fn (Activity $record) => view('filament.modals.activity-view', [
                        'record' => $record,
                    ])),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->latest();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),

        ];
    }

    // Доступ
    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return (bool) ($u?->hasAnyRole(['admin','manager','accountant']) || $u?->can('report.view'));
    }
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function shouldRegisterNavigation(): bool { return static::canViewAny(); }
}
