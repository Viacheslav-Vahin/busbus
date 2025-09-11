<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Користувачі';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('name')
                    ->label("Ім'я")
                    ->required(),

                Forms\Components\TextInput::make('surname')
                    ->label('Прізвище'),

                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),

                Forms\Components\TextInput::make('phone')
                    ->label('Телефон')
                    ->tel(),

                Forms\Components\TextInput::make('password')
                    ->label('Пароль')
                    ->helperText('Залиште порожнім — пароль не зміниться')
                    ->password()
                    ->revealable()
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create'),
            ]),

            Forms\Components\Select::make('role')
                ->label('Роль')
                ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'name'))
                ->required()
                ->helperText('Оберіть одну роль для користувача'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label("Ім'я")->searchable()->sortable(),
                Tables\Columns\TextColumn::make('surname')->label('Прізвище')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->label('Email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->label('Телефон')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TagsColumn::make('roles.name')->label('Ролі'),
                Tables\Columns\TextColumn::make('created_at')->label('Створено')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string { return 'Користувач'; }
    public static function getPluralModelLabel(): string { return 'Користувачі'; }
}
