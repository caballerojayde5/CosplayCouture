<?php

namespace App\Form;

use App\Entity\CustomOrder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

class CustomOrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customerName', TextType::class, [
                'label' => 'Customer Name',
                'required' => true,
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
            ])
            ->add('cosplayName', TextType::class, [
                'label' => 'Cosplay Name',
                'required' => true,
            ])
            ->add('bust', NumberType::class, [
                'label' => 'Bust (cm)',
                'required' => false,
                'scale' => 2,
            ])
            ->add('height', NumberType::class, [
                'label' => 'Height (cm)',
                'required' => false,
                'scale' => 2,
            ])
            ->add('hip', NumberType::class, [
                'label' => 'Hip (cm)',
                'required' => false,
                'scale' => 2,
            ])
            ->add('waist', NumberType::class, [
                'label' => 'Waist (cm)',
                'required' => false,
                'scale' => 2,
            ])
            ->add('specialRequest', TextareaType::class, [
                'label' => 'Special Request',
                'required' => false,
            ])
            ->add('address', TextType::class, [
                'label' => 'Address',
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Pending' => 'Pending',
                    'In Progress' => 'In Progress',
                    'Completed' => 'Completed',
                    'Cancelled' => 'Cancelled',
                ],
                'required' => true,
            ])
            ->add('price', NumberType::class, [
                'label' => 'Price',
                'required' => true,
                'scale' => 2,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CustomOrder::class,
        ]);
    }
}
