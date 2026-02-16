<?php

namespace App\Models;

use LdapRecord\Models\Model;

class EmailDto
{
    public ?string $dn;

    public ?string $sn;

    public ?string $uid;

    public ?string $cn;

    public ?string $givenName;

    public ?string $postalAddress;

    public ?string $postalCode;

    public ?string $l;

    public ?string $mail;

    public ?string $userPassword;

    public ?string $employeeNumber;

    public int $gosaMailQuota;

    public ?string $gosaMailForwardingAddress;

    public ?string $gosaMailAlternateAddress;

    public ?string $uidNumber;

    public ?string $gidNumber;

    public ?string $description;

    public array $alias = [];

    public string $alias_string = '';

    /**
     * Staff
     */
    public ?string $sAMAccountName;

    public ?string $extensionMail;

    public ?string $title;

    public ?string $info;

    public ?string $company;

    public ?string $mobile;

    public ?string $facsimileTelephoneNumber;

    public ?string $homePhone;

    public ?string $ipPhone;

    public ?string $telephoneNumber;

    public ?string $postOfficeBox;

    public ?string $streetAddress;

    public ?string $wWWHomePage;

    public ?string $initials;

    public ?string $homeDirectory;

    public ?string $homeDrive;

    public ?string $co;

    public array $proxyAddresses = [];

    public bool $force = false;

    public static function loadFromModel(Model $model)
    {
        $dto = new self;
        $dto->dn = $model->getDn();
        $dto->cn = $model->getFirstAttribute('cn');
        $dto->uid = $model->getFirstAttribute('uid');
        $dto->mail = $model->getFirstAttribute('mail');
        $dto->sn = $model->getFirstAttribute('sn');
        $dto->givenName = $model->getFirstAttribute('givenName');
        $dto->postalAddress = $model->getFirstAttribute('postalAddress');
        $dto->postalCode = $model->getFirstAttribute('postalCode');
        $dto->l = $model->getFirstAttribute('l');
        $dto->userPassword = $model->getFirstAttribute('userPassword');
        $dto->employeeNumber = $model->getFirstAttribute('employeeNumber');
        $dto->gosaMailQuota = $model->getFirstAttribute('gosaMailQuota', 250);
        $dto->gosaMailForwardingAddress = $model->getFirstAttribute('gosaMailForwardingAddress');
        $dto->gosaMailAlternateAddress = $model->getFirstAttribute('gosaMailAlternateAddress');
        $dto->uidNumber = $model->getFirstAttribute('uidNumber');
        $dto->gidNumber = $model->getFirstAttribute('gidNumber');
        $dto->description = $model->getFirstAttribute('description');
        $dto->alias = $model->getAttribute('gosaMailAlternateAddress', []);

        $dto->sAMAccountName = $model->getFirstAttribute('sAMAccountName');
        $dto->extensionMail = $model->getFirstAttribute('extensionMail');
        $dto->title = $model->getFirstAttribute('title');
        $dto->info = $model->getFirstAttribute('info');
        $dto->company = $model->getFirstAttribute('company');
        $dto->mobile = $model->getFirstAttribute('mobile');
        $dto->facsimileTelephoneNumber = $model->getFirstAttribute('facsimileTelephoneNumber');
        $dto->homePhone = $model->getFirstAttribute('homePhone');
        $dto->ipPhone = $model->getFirstAttribute('ipPhone');
        $dto->telephoneNumber = $model->getFirstAttribute('telephoneNumber');
        $dto->postOfficeBox = $model->getFirstAttribute('postOfficeBox');
        $dto->streetAddress = $model->getFirstAttribute('streetAddress');
        $dto->wWWHomePage = $model->getFirstAttribute('wWWHomePage');
        $dto->initials = $model->getFirstAttribute('initials');
        $dto->homeDirectory = $model->getFirstAttribute('homeDirectory');
        $dto->homeDrive = $model->getFirstAttribute('homeDrive');
        $dto->co = $model->getFirstAttribute('co');
        $dto->proxyAddresses = $model->getAttribute('proxyAddresses', []);

        return $dto;
    }
}
