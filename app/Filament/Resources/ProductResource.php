<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Support\ProductImageStorage;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationLabel = 'Productos';
    protected static ?string $modelLabel = 'Producto';
    protected static ?string $pluralModelLabel = 'Productos';
    protected static ?int $navigationSort = 2;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-shopping-bag';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Información básica')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug (URL)')
                        ->maxLength(255)
                        ->helperText('Se genera automáticamente desde el nombre.')
                        ->dehydrated()
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('category_id')
                        ->label('Categoría')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload(),

                    Forms\Components\TextInput::make('price')
                        ->label('Precio (S/)')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->prefix('S/'),

                    Forms\Components\TextInput::make('stock')
                        ->label('Stock')
                        ->required()
                        ->integer()
                        ->minValue(0)
                        ->default(0)
                        ->helperText('Para entradas y salidas con historial, usa el módulo Almacén.'),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('Orden')
                        ->integer()
                        ->minValue(0)
                        ->default(0),
                ]),

            Section::make('Contenido')
                ->schema([
                    Forms\Components\Textarea::make('description')
                        ->label('Descripción')
                        ->rows(4)
                        ->maxLength(2000)
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('image_path')
                        ->label('Imagen')
                        ->disk('s3')
                        ->image()
                        ->maxSize(2048) // 2 MB
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->directory(ProductImageStorage::DIRECTORY)
                        ->visibility('public')
                        ->fetchFileInformation(false)
                        ->saveUploadedFileUsing(
                            function (TemporaryUploadedFile $file, Get $get): ?string {
                                if (! $file->exists()) {
                                    return null;
                                }

                                return $file->storeAs(
                                    ProductImageStorage::DIRECTORY,
                                    ProductImageStorage::fileName(
                                        $file,
                                        slug: $get('slug'),
                                        name: $get('name'),
                                    ),
                                    ['disk' => 's3', 'visibility' => 'public'],
                                );
                            }
                        )
                        ->columnSpanFull(),
                ]),

            Section::make('Estado')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Activo')
                        ->default(true),

                    Forms\Components\Toggle::make('is_featured')
                        ->label('Destacado'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')->label('')->disk('s3'),
                Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category.name')->label('Categoría')->sortable(),
                Tables\Columns\TextColumn::make('price')->label('Precio')->money('PEN')->sortable(),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stock')
                    ->sortable()
                    ->badge()
                    ->color(fn (Product $record): string => match ($record->stock_status) {
                        'out' => 'danger',
                        'low' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')->label('Activo')->boolean(),
                Tables\Columns\IconColumn::make('is_featured')->label('Destacado')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categoría')
                    ->relationship('category', 'name'),
                Tables\Filters\TernaryFilter::make('is_active')->label('Activo'),
                Tables\Filters\TernaryFilter::make('is_featured')->label('Destacado'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
