<?php

class Inventory
{
    public $tier0;
    public $tier1;
    public $tier2;
    public $tier3;
    public $score;

    public function __construct()
    {
        fscanf(STDIN, "%d %d %d %d %d", $inv0, $inv1, $inv2, $inv3, $score);

        $this->tier0 = $inv0;
        $this->tier1 = $inv1;
        $this->tier2 = $inv2;
        $this->tier3 = $inv3;
        $this->score = $score;
    }

    public function countInventoryUsed()
    {
        return $this->tier0 + $this->tier1 + $this->tier2 + $this->tier3;
    }

    /**
     * @param $orderPrice
     * @return mixed
     */
    public function getFinalScore($orderPrice = 0)
    {
        return $this->score + $this->countInventoryUsed() - $this->tier0 + $orderPrice;
    }
}


class Action
{
    public $actionId;
    public $actionType;
    public $delta0;
    public $delta1;
    public $delta2;
    public $delta3;
    public $price;
    public $tomeIndex;
    public $taxCount;
    public $castable;
    public $simulationCastable;
    public $repeatable;
    public $toRepeat;
    public $bonus;

    public function __construct()
    {
        fscanf(STDIN, "%d %s %d %d %d %d %d %d %d %d %d", $actionId, $actionType, $delta0, $delta1, $delta2, $delta3, $price, $tomeIndex, $taxCount, $castable, $repeatable);

        $this->actionId = $actionId;
        $this->actionType = $actionType;
        $this->delta0 = $delta0;
        $this->delta1 = $delta1;
        $this->delta2 = $delta2;
        $this->delta3 = $delta3;
        $this->price = $price;
        $this->tomeIndex = $tomeIndex;
        $this->taxCount = $taxCount;
        $this->castable = $castable;
        $this->simulationCastable = $castable;
        $this->repeatable = $repeatable;
        $this->toRepeat = false;
        $this->bonus = 0;
    }

    /**
     * @param Inventory $inv
     * @param array $limit
     * @return bool
     */
    public function canThrowSpell($inv, $limit = [])
    {
        $isInLimit0 = (!empty($limit)) ? $this->delta0 < 0 || ($this->delta0 + $inv->tier0) <= $limit['delta0'] : true;
        $isInLimit1 = (!empty($limit)) ? $this->delta1 < 0 || ($this->delta1 + $inv->tier1) <= $limit['delta1'] : true;
        $isInLimit2 = (!empty($limit)) ? $this->delta2 < 0 || ($this->delta2 + $inv->tier2) <= $limit['delta2'] : true;
        $isInLimit3 = (!empty($limit)) ? $this->delta3 < 0 || ($this->delta3 + $inv->tier3) <= $limit['delta3'] : true;

        $isDelta0 = $this->delta0 === 0 || (($inv->tier0 + $this->delta0) >= 0 && $isInLimit0);
        $isDelta1 = $this->delta1 === 0 || (($inv->tier1 + $this->delta1) >= 0 && $isInLimit1);
        $isDelta2 = $this->delta2 === 0 || (($inv->tier2 + $this->delta2) >= 0 && $isInLimit2);
        $isDelta3 = $this->delta3 === 0 || (($inv->tier3 + $this->delta3) >= 0 && $isInLimit3);
        $size = $this->delta0 + $this->delta1 + $this->delta2 + $this->delta3 + $inv->countInventoryUsed();

        return $isDelta0 && $isDelta1 && $isDelta2 && $isDelta3 && intval($size) <= 10 && $this->castable;
    }

    public function getPriceWOBonus()
    {
        return $this->price - $this->bonus;
    }

    public function getMalus()
    {
        $sumMalus = ($this->delta0 >= 0) ? 0 : abs($this->delta0);
        $sumMalus += (($this->delta1 >= 0) ? 0 : abs($this->delta1) * 2);
        $sumMalus += (($this->delta2 >= 0) ? 0 : abs($this->delta2 * 3));
        $sumMalus += (($this->delta3 >= 0) ? 0 : abs($this->delta3 * 4));

        return $sumMalus;
    }

    public function getBonus()
    {
        $sumMalus = ($this->delta0 > 0) ? $this->delta0 : 0;
        $sumMalus += (($this->delta1 > 0) ? $this->delta1 * 2 : 0);
        $sumMalus += (($this->delta2 > 0) ? $this->delta2 * 3 : 0);
        $sumMalus += (($this->delta3 > 0) ? $this->delta3 * 4 : 0);

        return $sumMalus;
    }
}


class Manager
{
    public $orders = [];
    public $spellsByTier = [];
    public $spellsToLearn = [];
    public $countSpells = 0;
    public $myInventory;
    public $enemyInventory;

    const TIER0 = 'tier0';
    const TIER1 = 'tier1';
    const TIER2 = 'tier2';
    const TIER3 = 'tier3';

    public function __construct()
    {
        fscanf(STDIN, "%d", $actionCount);
        for ($i = 0; $i < $actionCount; $i++) {
            $action = new Action();
            if ($action->actionType === 'BREW') {
                $action->bonus = (count($this->orders) === 0) ? 3 : 0;
                $action->bonus = (count($this->orders) === 1) ? 1 : $action->bonus;
                $this->orders[intval($action->price . count($this->orders))] = $action;
            }
            if ($action->actionType === 'CAST') {
                foreach ($this->getTiers($action) as $tier) {
                    $this->spellsByTier[$tier][] = $action;
                }
                $this->countSpells++;
            }
            if ($action->actionType === 'LEARN') {
                $this->spellsToLearn[] = $action;
            }
        }
        krsort($this->orders);

        $this->myInventory = new Inventory();
        $this->enemyInventory = new Inventory();
    }

    public function getMoreProfitableOrder($countOrderMade)
    {
        $craftableOrders = $this->getCraftableOrders();

        foreach ($craftableOrders as $order) {
            if ($this->shouldMakeOrder($order, $countOrderMade)) {
                return $order;
            }
        }

        return null;
    }

    public function getCraftableOrders()
    {
        $craftableOrders = [];
        foreach ($this->orders as $key => $order) {
            $canTier0 = $this->myInventory->tier0 >= abs($order->delta0);
            $canTier1 = $this->myInventory->tier1 >= abs($order->delta1);
            $canTier2 = $this->myInventory->tier2 >= abs($order->delta2);
            $canTier3 = $this->myInventory->tier3 >= abs($order->delta3);

            if ($canTier0 && $canTier1 && $canTier2 && $canTier3) {
                $craftableOrders[$key] = $order;
            }
        }

        return $craftableOrders;
    }

    /**
     * @param Action $order
     * @param $countOrderMade
     * @return bool
     */
    public function shouldMakeOrder($order, $countOrderMade)
    {
        $currentPrice = $order->price;

        if ($this->isBestOrder($currentPrice)) {
            return true;
        }
        $currentPrice = $currentPrice + $order->delta1 + $order->delta2 + $order->delta3;

        if ($countOrderMade === 5 && $this->myInventory->getFinalScore($currentPrice) <= $this->enemyInventory->getFinalScore()) {
            return false;
        }

        return true;
    }

    /**
     * @param $currentPrice
     * @return bool
     */
    public function isBestOrder($currentPrice)
    {
        foreach (array_keys($this->orders) as $price) {
            if ($price > $currentPrice) {
                return false;
            }
        }

        return true;
    }

    public function hasNoCastableSpells()
    {
        foreach ($this->spellsByTier as $spells) {
            foreach ($spells as $spell) {
                if (!$spell->castable) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param $countOrderMade
     * @return mixed
     */
    public function orderToMake($countOrderMade)
    {
        $ordersByRealPrice = [];
        $suffix = 0;
        /** @var Action $order */
        foreach ($this->orders as $order) {
            $ordersByRealPrice[$order->getPriceWOBonus() . $suffix] = $order;
            $suffix++;
        }

        ksort($ordersByRealPrice);
        if ($countOrderMade === 5) {
            foreach ($ordersByRealPrice as $order) {
                if ($this->shouldMakeOrder($order, $countOrderMade)) {
                    return $order;
                }
            }
        }

        ksort($this->orders);
        return array_values($ordersByRealPrice)[0];
//        return end($this->orders);
    }

    /**
     * @param Action $bestSpell
     * @return bool
     */
    public function shouldLeanSpell($bestSpell)
    {
        $spellToLearn = $this->canLearnSpell();
        return !is_null($spellToLearn) && $bestSpell->delta0 > 0 && $spellToLearn->taxCount > 0;
    }

    /**
     * @param Action $spellToLearn
     * @param bool $checkCount
     * @return Action|null
     */
    public function canLearnSpell($spellToLearn = null, $checkCount = false)
    {
        $currentBonus = 0;
        if (empty($spellToLearn)) {
            /** @var Action $spell */
            foreach ($this->spellsToLearn as $priceTier0 => $spell) {
                $spellBonus = $spell->getBonus();
                if ($spell->getMalus() === 0 && $currentBonus < $spellBonus && $priceTier0 <= $this->myInventory->tier0 && $priceTier0 < 3) {
                    $spellToLearn = $spell;
                    $currentBonus = $spellBonus;
                    break;
                }
            }

            if (empty($spellToLearn)) {
                $spellToLearn = $this->getSpellToLearn();
            }
        }

        if (!empty($spellToLearn) && $spellToLearn->taxCount + $this->myInventory->countInventoryUsed() <= 10) {
            if (($checkCount && $this->countSpells < 11) || $currentBonus > 0) {
                return $spellToLearn;
            }
        }

        return null;
    }

    public function getSpellToLearn()
    {
        if (!empty($this->spellsToLearn[0])) {
            return $this->spellsToLearn[0];
        }

        return null;
    }

    /**
     * @param Action $order
     * @return mixed|null
     */
    public function getBestSpell($order)
    {
        $inventoryAfter = [
            self::TIER0 => $this->myInventory->tier0 + $order->delta0,
            self::TIER1 => $this->myInventory->tier1 + $order->delta1,
            self::TIER2 => $this->myInventory->tier2 + $order->delta2,
            self::TIER3 => $this->myInventory->tier3 + $order->delta3,
        ];

        return self::getSpell($inventoryAfter);
    }

    public function getSpell($inventoryAfter, $extraSpellsIds = [])
    {
        $lastSpell = null;
        foreach ($inventoryAfter as $tierLabel => $tierCount) {
            if ($tierCount < 0 && !empty($this->spellsByTier[$tierLabel])) {
                $tierSpells = $this->getSpellsByMinus($this->spellsByTier[$tierLabel], $inventoryAfter);
                /** @var Action $spell */
                foreach ($tierSpells as $minus => $spell) {
                    if ($spell->castable && $spell->simulationCastable) {
                        $lastSpell = $spell;
                    }
                    if ($spell->canThrowSpell($this->myInventory)) {
                        return $spell;
                    }
                }
            }
        }

        if (!isset($lastSpell) || in_array($lastSpell->actionId, $extraSpellsIds)) {
            return null;
        }

        $inventoryAfter[self::TIER0] += ((($lastSpell->delta0) < 0) ? $lastSpell->delta0 : 0);
        $inventoryAfter[self::TIER1] += ((($lastSpell->delta1) < 0) ? $lastSpell->delta1 : 0);
        $inventoryAfter[self::TIER2] += ((($lastSpell->delta2) < 0) ? $lastSpell->delta2 : 0);
        $inventoryAfter[self::TIER3] += ((($lastSpell->delta3) < 0) ? $lastSpell->delta3 : 0);
        $lastSpell->simulationCastable = false;
        $extraSpellsIds[] = $lastSpell->actionId;

        return self::getSpell($inventoryAfter, $extraSpellsIds);
    }

    public function getSpellsByMinus($spells, $inventoryAfter)
    {
        $tierSpells = [];
        $suffix = 1000;

        /** @var Action $spell */
        foreach ($spells as $spell) {
            $tierSpells[$this->getSpellMinus($spell, $inventoryAfter) + $suffix] = $spell;
        }
        ksort($tierSpells);

        return $tierSpells;
    }

    /**
     * @param Action $spell
     * @param $inventoryAfter
     * @return int
     */
    public function getSpellMinus($spell, $inventoryAfter)
    {
        $weight = $spell->getMalus();
        $weight += (($inventoryAfter[self::TIER0] + $spell->delta0 < 0) ? abs($spell->delta0) : 0);
        $weight += (($inventoryAfter[self::TIER1] + $spell->delta1 < 0) ? abs($spell->delta1) * 2 : 0);
        $weight += (($inventoryAfter[self::TIER2] + $spell->delta2 < 0) ? abs($spell->delta2) * 3 : 0);
        $weight += (($inventoryAfter[self::TIER3] + $spell->delta3 < 0) ? abs($spell->delta3) * 4 : 0);

        return $weight;
    }

    /**
     * @param Action $spell
     * @return array
     */
    public function getTiers($spell)
    {
        $tiers = [];

        foreach ($this->getValueByTier($spell) as $tierLabel => $value) {
            if ($value > 0) {
                $tiers[] = $tierLabel;
            }
        }

        return $tiers;
    }

    public function getValueByTier($entity)
    {
        if ($entity instanceof Inventory) {
            return [
                self::TIER0 => $entity->tier0,
                self::TIER1 => $entity->tier1,
                self::TIER2 => $entity->tier2,
                self::TIER3 => $entity->tier3,
            ];
        }
        if ($entity instanceof Action) {
            return [
                self::TIER0 => $entity->delta0,
                self::TIER1 => $entity->delta1,
                self::TIER2 => $entity->delta2,
                self::TIER3 => $entity->delta3,
            ];
        }

        return [];
    }

    /**
     * @param Inventory $inventory
     * @param Action $spell
     * @param int $times
     * @return bool
     */
    public function repeatSpell($inventory, $spell, $times = 1)
    {
        if (!$spell->repeatable) {
            return 1;
        }

        $clone = clone $inventory;
        $clone->tier0 += $spell->delta0 * 2;
        $clone->tier1 += $spell->delta1 * 2;
        $clone->tier2 += $spell->delta2 * 2;
        $clone->tier3 += $spell->delta3 * 2;

        if ($clone->tier0 > 0 && $clone->tier1 > 0 && $clone->tier2 > 0 && $clone->tier3 > 0 && $clone->countInventoryUsed() <= 10) {
            $times = $this->repeatSpell($clone, $spell, $times + 1);
        }

        return $times;
    }
}

$countOrderMade = 0;
$mustRest = false;
while (TRUE) {
    $hasResponded = false;
    $gameManager = new Manager();

    $moreProfitableOrder = $gameManager->getMoreProfitableOrder($countOrderMade);
    if (!$hasResponded && $moreProfitableOrder !== null) {
        echo("BREW $moreProfitableOrder->actionId BREW $moreProfitableOrder->actionId \n");
        $countOrderMade++;
        $hasResponded = true;
        $mustRest = true;
    }

    if (!$hasResponded && !empty($spellToLearn = $gameManager->canLearnSpell(null, true)) /*&& $spellToLearn->getMalus() === 0*/) {
        echo("LEARN $spellToLearn->actionId LEARN $spellToLearn->actionId Until 10 \n");
        $hasResponded = true;
    }

    $orderToMake = $gameManager->orderToMake($countOrderMade);
    error_log('aimed order : ' . ((is_null($orderToMake)) ? '' : $orderToMake->actionId));

    /** @var Action $bestSpell */
    $bestSpell = $gameManager->getBestSpell($orderToMake);
    $spellToLearn = $gameManager->getSpellToLearn();
    if (!$hasResponded && !is_null($bestSpell) && $gameManager->shouldLeanSpell($bestSpell)) {
        echo("LEARN $spellToLearn->actionId LEARN $spellToLearn->actionId to get some tier0 ! \n");
        $hasResponded = true;
    }

    if (!$hasResponded && !is_null($bestSpell)) {
        $repeat = $gameManager->repeatSpell($gameManager->myInventory, $bestSpell);
        echo("CAST $bestSpell->actionId $repeat CAST $bestSpell->actionId $repeat \n");
        $hasResponded = true;
    }

    if (!$hasResponded && $gameManager->hasNoCastableSpells()) {
        echo("REST \n");
        $hasResponded = true;
    }

    if (!$hasResponded && !is_null($spellToLearn)) {
        echo("LEARN $spellToLearn->actionId LEARN $spellToLearn->actionId Better learn then nothing ! \n");
        $hasResponded = true;
    }

    if (!$hasResponded) {
        echo("WAIT WAIT \n");
    }
}