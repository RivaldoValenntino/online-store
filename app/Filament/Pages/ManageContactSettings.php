<?php

namespace App\Filament\Pages;

use App\Services\ContactSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageContactSettings extends Page
{
    use InteractsWithFormActions;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan Toko';

    protected static ?string $navigationLabel = 'Informasi Kontak';

    protected static ?string $title = 'Informasi Kontak & Customer Service';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChatBubbleLeftRight;

    protected static ?int $navigationSort = 2;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(ContactSettings::class)->all();

        $this->form->fill($settings);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Customer Service WhatsApp')
                    ->description('Pengaturan kontak WhatsApp Customer Service resmi yang digunakan untuk menerima komplain dan pertanyaan dari pelanggan.')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextInput::make('cs_name')
                                ->label('Nama Customer Service')
                                ->placeholder('Contoh: CS ' . (config('services.apps.app_name') ?: config('app.name', 'Podo Raos')))
                                ->helperText('Nama PIC / agen yang akan tertera sebagai identitas layanan CS.')
                                ->maxLength(100),

                            TextInput::make('cs_phone')
                                ->label('Nomor WhatsApp Customer Service')
                                ->tel()
                                ->required()
                                ->placeholder('Contoh: 08123456789 atau 628123456789')
                                ->helperText('Nomor WhatsApp aktif. Dapat diawali 08, 62, atau +62. Sistem otomatis mengonversi ke format internasional (628xxxxxxxxxx).')
                                ->maxLength(30),
                        ]),

                        Toggle::make('cs_is_active')
                            ->label('Status Layanan Customer Service Aktif')
                            ->helperText('Jika dinonaktifkan, tombol "Ajukan Komplain via WhatsApp" pada detail pesanan pelanggan akan disembunyikan/dinonaktifkan secara aman.')
                            ->default(true),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment(Alignment::Start),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan Pengaturan')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $rawPhone = $state['cs_phone'] ?? null;
        $normalized = ContactSettings::normalizePhone($rawPhone);

        if (blank($normalized)) {
            $this->addError('data.cs_phone', 'Nomor WhatsApp tidak valid. Masukkan nomor HP Indonesia yang benar (minimal 9 digit, diawali 08 atau 62).');

            return;
        }

        $state['cs_phone'] = $normalized;

        app(ContactSettings::class)->save($state);

        $this->form->fill(app(ContactSettings::class)->all());

        Notification::make()
            ->title('Pengaturan informasi kontak berhasil disimpan')
            ->success()
            ->send();
    }
}
