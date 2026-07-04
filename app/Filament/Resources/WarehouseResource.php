<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Product;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use InvalidArgumentException;

class WarehouseResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $slug = 'almacen';

    protected static ?string $navigationLabel = 'Almacén';

    protected static ?string $modelLabel = 'producto en almacén';

    protected static ?string $pluralModelLabel = 'Almacén';

    protected static ?int $navigationSort = 3;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-archive-box';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('')
                    ->disk('s3')
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stock actual')
                    ->sortable()
                    ->numeric()
                    ->alignEnd()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('stock_status_label')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (Product $record): string => match ($record->stock_status) {
                        'out' => 'danger',
                        'low' => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('latestStockMovement.created_at')
                    ->label('Último movimiento')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categoría')
                    ->relationship('category', 'name'),
                Tables\Filters\Filter::make('low_stock')
                    ->label('Stock bajo')
                    ->query(fn ($query) => $query->lowStock()),
                Tables\Filters\Filter::make('out_of_stock')
                    ->label('Sin stock')
                    ->query(fn ($query) => $query->outOfStock()),
            ])
            ->recordActions([
                ActionGroup::make([
                    static::entryAction(),
                    static::exitAction(),
                    static::adjustAction(),
                ])
                    ->label('Movimiento')
                    ->icon('heroicon-o-arrows-right-left')
                    ->button(),
            ])
            ->defaultSort('stock', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouse::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    protected static function entryAction(): Action
    {
        return Action::make('entry')
            ->label('Entrada')
            ->icon('heroicon-o-plus-circle')
            ->color('success')
            ->schema([
                Forms\Components\TextInput::make('quantity')
                    ->label('Cantidad')
                    ->integer()
                    ->minValue(1)
                    ->required(),
                Forms\Components\TextInput::make('reason')
                    ->label('Motivo')
                    ->placeholder('Ej. Reposición de proveedor')
                    ->maxLength(255),
                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->rows(2)
                    ->maxLength(1000),
            ])
            ->action(function (Product $record, array $data): void {
                static::runMovement(
                    fn (InventoryService $inventory) => $inventory->recordEntry(
                        $record,
                        (int) $data['quantity'],
                        $data['reason'] ?? null,
                        $data['notes'] ?? null,
                        auth()->user(),
                    ),
                    'Entrada registrada',
                );
            });
    }

    protected static function exitAction(): Action
    {
        return Action::make('exit')
            ->label('Salida')
            ->icon('heroicon-o-minus-circle')
            ->color('danger')
            ->schema([
                Forms\Components\TextInput::make('quantity')
                    ->label('Cantidad')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(fn (Product $record): int => $record->stock)
                    ->required()
                    ->helperText(fn (Product $record): string => "Stock disponible: {$record->stock}"),
                Forms\Components\TextInput::make('reason')
                    ->label('Motivo')
                    ->placeholder('Ej. Venta, muestra, pérdida')
                    ->maxLength(255),
                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->rows(2)
                    ->maxLength(1000),
            ])
            ->action(function (Product $record, array $data): void {
                static::runMovement(
                    fn (InventoryService $inventory) => $inventory->recordExit(
                        $record,
                        (int) $data['quantity'],
                        $data['reason'] ?? null,
                        $data['notes'] ?? null,
                        auth()->user(),
                    ),
                    'Salida registrada',
                );
            });
    }

    protected static function adjustAction(): Action
    {
        return Action::make('adjust')
            ->label('Ajustar stock')
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('warning')
            ->schema([
                Forms\Components\TextInput::make('new_stock')
                    ->label('Stock real')
                    ->integer()
                    ->minValue(0)
                    ->required()
                    ->default(fn (Product $record): int => $record->stock)
                    ->helperText(fn (Product $record): string => "Stock actual: {$record->stock}"),
                Forms\Components\Textarea::make('notes')
                    ->label('Motivo del ajuste')
                    ->rows(2)
                    ->maxLength(1000),
            ])
            ->action(function (Product $record, array $data): void {
                static::runMovement(
                    fn (InventoryService $inventory) => $inventory->adjustStock(
                        $record,
                        (int) $data['new_stock'],
                        $data['notes'] ?? null,
                        auth()->user(),
                    ),
                    'Stock ajustado',
                );
            });
    }

    protected static function runMovement(callable $callback, string $successTitle): void
    {
        try {
            $callback(app(InventoryService::class));

            Notification::make()
                ->success()
                ->title($successTitle)
                ->send();
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->danger()
                ->title('No se pudo actualizar el stock')
                ->body($exception->getMessage())
                ->send();
        }
    }
}
