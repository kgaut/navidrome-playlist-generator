<?php

namespace App\Service;

use App\Entity\Setting;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;

class SettingsService
{
    public const KEY_DEFAULT_LIMIT = 'default_limit';
    public const KEY_DEFAULT_NAME_TEMPLATE = 'default_playlist_name_template';

    public const DEFAULT_LIMIT = 50;
    public const DEFAULT_NAME_TEMPLATE = '{label} — {date}';

    public function __construct(
        private readonly SettingRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getDefaultLimit(): int
    {
        $s = $this->repository->findOneByKey(self::KEY_DEFAULT_LIMIT);
        if ($s === null) {
            return self::DEFAULT_LIMIT;
        }
        $v = (int) $s->getValue();
        return $v > 0 ? $v : self::DEFAULT_LIMIT;
    }

    public function getDefaultNameTemplate(): string
    {
        $s = $this->repository->findOneByKey(self::KEY_DEFAULT_NAME_TEMPLATE);
        return $s !== null && $s->getValue() !== '' ? $s->getValue() : self::DEFAULT_NAME_TEMPLATE;
    }

    public function setDefaultLimit(int $limit): void
    {
        $this->set(self::KEY_DEFAULT_LIMIT, (string) $limit);
    }

    public function setDefaultNameTemplate(string $template): void
    {
        $this->set(self::KEY_DEFAULT_NAME_TEMPLATE, $template);
    }

    private function set(string $key, string $value): void
    {
        $s = $this->repository->findOneByKey($key);
        if ($s === null) {
            $s = new Setting($key, $value);
            $this->em->persist($s);
        } else {
            $s->setValue($value);
        }
        $this->em->flush();
    }
}
