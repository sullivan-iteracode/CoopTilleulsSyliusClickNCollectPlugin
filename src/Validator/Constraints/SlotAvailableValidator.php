<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Les-Tilleuls.coop <contact@les-tilleuls.coop>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace CoopTilleuls\SyliusClickNCollectPlugin\Validator\Constraints;

use CoopTilleuls\SyliusClickNCollectPlugin\Entity\ClickNCollectShipmentInterface;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Checks if this time slot is available.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class SlotAvailableValidator extends ConstraintValidator
{
    private ManagerRegistry $managerRegistry;
    private $computer;
    private string $shipmentClass;

    public function __construct(ManagerRegistry $managerRegistry, callable $computer, string $shipmentClass)
    {
        $this->managerRegistry = $managerRegistry;
        $this->computer = $computer;
        $this->shipmentClass = $shipmentClass;
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof SlotAvailable) {
            throw new UnexpectedTypeException($constraint, Rrule::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof ClickNCollectShipmentInterface) {
            throw new UnexpectedValueException($value, ClickNCollectShipmentInterface::class);
        }

        if (null === $collectionTime = $value->getCollectionTime()) {
            return;
        }

        $previousValue = $this->managerRegistry->getManagerForClass($this->shipmentClass)->getUnitOfWork()->getOriginalEntityData($value)['collectionTime'];
        if ($collectionTime == $previousValue) {
            return;
        }

        foreach (($this->computer)($value, $value->getPlace(), $collectionTime->sub(new \DateInterval('PT1S')), $collectionTime->add(new \DateInterval('PT1S'))) as $recurrence) {
            if ($collectionTime == $recurrence->getStart()) {
                return;
            }
        }

        $this->context
            ->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $collectionTime->format(\DateTime::ATOM))
            ->addViolation();
    }
}
