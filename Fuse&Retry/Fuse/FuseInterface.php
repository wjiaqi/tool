<?php


namespace App\Kernel\Fuse;


interface FuseInterface
{
    public function state(): State;

    public function attempt(): bool;

    public function getLastResult(): array;

}