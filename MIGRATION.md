Ldap to sql users

The sql table is populate with command php artisan citoyen:sync

Password from ldap is store in legacy_password (ssha)

Sql entry is generate by Citoyen::generateDataFromLdap

When user or admin changed password, it is store in userPassword (SHA512-CRYPT)

Password is change by CitoyenHandler::changePassword
legay_password is set to null

The password is changed from 

#Admin page
must be change on ldap, and then on sql (userPassword)
App\Filament\Resources\Citoyens\Pages\ViewCitoyen

#My space citoyen
only change sql password (userPassword)
App\Filament\Citoyen\Pages\ChangePassword

#Wizard citoyen 
only change sql password (userPassword)
App\Filament\Citoyen\Pages\Onboarding

#Command
must be change on ldap, and then on sql (userPassword)
App\Console\Commands\PasswordCommand

