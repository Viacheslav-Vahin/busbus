<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GalleryPhotoResource\Pages;
use App\Models\GalleryPhoto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;

class GalleryPhotoResource extends Resource
{
    protected static ?string $model = GalleryPhoto::class;

    protected static ?string $navigationIcon  = 'heroicon-o-photo';
    protected static ?string $navigationLabel = 'Галерея';
    protected static ?string $slug = 'gallery';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(12)->schema([
                FileUpload::make('path')
                    ->label('Фото')
                    ->image()
                    ->imageEditor()
                    ->directory('gallery')
                    ->required()
                    ->columnSpan(6),

                TextInput::make('title')
                    ->label('Заголовок')
                    ->maxLength(255)
                    ->columnSpan(6),

                TagsInput::make('tags')
                    ->label('Теги')
                    ->suggestions(['bus','nature','city','team'])
                    ->columnSpan(8),

                Toggle::make('is_published')
                    ->label('Показувати')
                    ->default(true)
                    ->columnSpan(4),

                Textarea::make('placeholder')
                    ->label('Опис/alt')
                    ->rows(3)
                    ->columnSpan(12),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('path')->label('Превʼю')->square(),
                TextColumn::make('title')->label('Заголовок')->limit(40)->searchable(),
                TextColumn::make('tags')->label('Теги')
                    ->formatStateUsing(fn ($state) => $state ? implode(', ', (array)$state) : '—'),
                ToggleColumn::make('is_published')->label('Показувати'),
                TextColumn::make('created_at')->dateTime('d.m.Y H:i')->label('Створено'),
            ])
            ->defaultSort('created_at','desc')
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
            'index' => Pages\ListGalleryPhotos::route('/'),
            'create' => Pages\CreateGalleryPhoto::route('/create'),
            'edit' => Pages\EditGalleryPhoto::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Контент';
    }
}
