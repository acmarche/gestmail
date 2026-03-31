<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Bienvenue dans votre Espace Citoyen
        </x-slot>
        <x-slot name="description">
            Gérez votre compte de messagerie citoyenne
        </x-slot>

        <div class="prose dark:prose-invert max-w-none text-sm">
            <p>Depuis cet espace, vous pouvez :</p>
            <ul>
                <li><strong>Changer votre mot de passe</strong> de messagerie à tout moment.</li>
                <li><strong>Renseigner un email ou téléphone de secours</strong> pour récupérer l'accès à votre compte en cas de perte de mot de passe.</li>
            </ul>
        </div>

        <div class="mt-4">
            <x-filament::link href="{{ \App\Filament\Citoyen\Pages\Charter::getUrl() }}" color="gray" icon="heroicon-o-document-text">
                Consulter la charte d'utilisation
            </x-filament::link>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
