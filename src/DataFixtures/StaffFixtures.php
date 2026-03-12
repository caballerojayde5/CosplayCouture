<?php

namespace App\DataFixtures;

use App\Entity\Staff;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class StaffFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $staff = new Staff();
        $staff->setEmail('staff@cosplaycouture');
        $staff->setRoles([]);

        $hashedPassword = $this->passwordHasher->hashPassword(
            $staff,
            'staff123'
        );
        $staff->setPassword($hashedPassword);

        $manager->persist($staff);
        $manager->flush();
    }
}
