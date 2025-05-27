<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),
                FileUpload::make('file_path')
                    ->label('Archivo')
                    ->helperText('Sube un archivo PDF, imagen (jpeg, jpg, png, gif) o documento de Office (Word, Excel, PowerPoint).')
                    ->disk('public')
                    ->directory('documents')
                    ->acceptedFileTypes([
                        'application/pdf', // PDF
                        'image/jpeg', // imagenes JPEG
                        'image/jpg', // imagenes JPG
                        'image/png', // imagenes PNG
                        'image/gif', // imagenes GIF
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',  // Word 2007+
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // Excel 2007+
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation', // PowerPoint 2007+
                    ])
                    ->required(),

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Documento')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->label('Descargar')
                    ->url(fn($record) => asset('storage/' . $record->file_path))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
