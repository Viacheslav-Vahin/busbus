<?php
// app/Filament/Resources/CmsPageResource.php
namespace App\Filament\Resources;

use App\Models\CmsPage;
use Filament\Forms;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Textarea;
use Filament\Tables;
use Filament\Resources\Resource;
use App\Filament\Resources\CmsPageResource\Pages;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\RichEditor;

class CmsPageResource extends Resource {
    protected static ?string $model = CmsPage::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel  = 'Cms Сторінки';
    protected static ?string $navigationGroup  = 'Cms';
    public static function form(Forms\Form $form): Forms\Form {
        return $form->schema([
            Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord:true),
            Forms\Components\KeyValue::make('title')->label('Title (локаліз.)')->keyLabel('locale')->valueLabel('value'),
            Builder::make('blocks')->blocks([
                Builder\Block::make('hero')->schema([
                    Forms\Components\TextInput::make('title')->label('Заголовок')->required(),
                    Forms\Components\TextInput::make('subtitle')->label('Підзаголовок'),
                    Forms\Components\TextInput::make('cta_text')->label('Текст кнопки')->default('Почати'),
                    Forms\Components\TextInput::make('cta_href')->default('#booking-form'),
                ]),
                Builder\Block::make('booking_form'), // просто плейсхолдер, фронт сам вставить компонент
                Builder\Block::make('benefits')->schema([
                    Forms\Components\Repeater::make('items')->schema([
                        Forms\Components\TextInput::make('icon')->default('online'),
                        Forms\Components\TextInput::make('title')->required(),
                        Textarea::make('text'),
                    ])->createItemButtonLabel('Додати перевагу')->collapsed()
                ]),
                Builder\Block::make('how_it_works')->schema([
                    Forms\Components\Repeater::make('steps')->schema([
                        Forms\Components\TextInput::make('icon')->default('clock'),
                        Forms\Components\TextInput::make('title'),
                        Textarea::make('text'),
                    ])->collapsed()
                ]),
                Builder\Block::make('faq')->schema([
                    Forms\Components\Repeater::make('items')->schema([
                        Forms\Components\TextInput::make('q')->label('Питання'),
                        Textarea::make('a')->label('Відповідь'),
                    ])->collapsed()
                ]),
                Builder\Block::make('trust_bar'),
                Builder\Block::make('help_cta')->schema([
                    Forms\Components\TextInput::make('text')->default('Потрібна допомога?'),
                ]),
                Builder\Block::make('rich_text')               // type = "rich_text"
                ->label('HTML / Rich text')
                    ->icon('heroicon-m-document-text')
                    ->schema([
                        RichEditor::make('html')       // збережеться у data.html
                        ->label('Вміст (HTML)')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold','italic','underline','strike','link',
                                'orderedList','bulletList','blockquote','codeBlock',
                                'h2','h3','h4','hr','undo','redo',
                            ])
                            ->maxLength(200000),
                    ]),
                ])->collapsible(),
            Forms\Components\Toggle::make('status')->label('Опубліковано')->inline(false)
                ->onIcon('heroicon-m-check')->offIcon('heroicon-m-x-mark')
                ->afterStateUpdated(fn($state, $record)=>$record->update(['status'=>$state?'published':'draft'])),
        ]);
    }
    public static function table(Tables\Table $table): Tables\Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('id')->sortable(),
            Tables\Columns\TextColumn::make('slug')->searchable(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('updated_at')->dateTime(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCmsPages::route('/'),
            'create' => Pages\CreateCmsPage::route('/create'),
            'edit'   => Pages\EditCmsPage::route('/{record}/edit'),
        ];
    }
}
