<?php

namespace tests;
use PHPUnit\Framework\TestCase;
use src\model\Annonce;

class AnnonceTest extends TestCase
{
    public function testAnnonceCreation()
    {
        $annonce = new Annonce();
        $this->assertInstanceOf(Annonce::class, $annonce);
    }

    public function testAnnonceProperties()
    {
        $annonce = new Annonce();
        $annonce->setTitle('Test Title');
        $this->assertEquals('Test Title', $annonce->getTitle());
    }
}