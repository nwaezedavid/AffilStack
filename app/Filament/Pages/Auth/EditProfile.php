<?php

namespace App\Filament\Pages\Auth;

use App\Services\Media\AvatarProcessor;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Admin → user menu → Profile: name, email, password and a profile photo
 * (shown in the admin top bar instead of initials, and on the customer
 * dashboard for admins who also use the app). Photos go through the same
 * AvatarProcessor as customers' (square WebP, EXIF stripped).
 */
class EditProfile extends BaseEditProfile
{
    public static function isSimple(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('avatar_path')
                ->label('Profile photo')
                ->image()
                ->avatar()
                ->disk('public')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                ->maxSize(5120)
                ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(AvatarProcessor::class)->store($file, (int) $this->getUser()->getAuthIdentifier()))
                ->deleteUploadedFileUsing(fn (string $file) => app(AvatarProcessor::class)->delete($file)),
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }
}
