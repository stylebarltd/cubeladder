<?php
/**
 * Player card for the hover on the rankings and the Hall of Fame
 * (PlayersController::hoverCard), in the player card style.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Player $player
 * @var \Cake\Datasource\EntityInterface|null $rating
 * @var int $ratedPlayers
 * @var array $progress PlayerMilestoneProgress totals
 * @var string|null $lastSeen
 * @var array $badges last week's achievements
 * @var array $weapons PlayersController::weaponsOfChoice()
 * @var array|null $map most played map ['name', 'n']
 */
?>
<?= $this->element('player_card_hover', compact('player', 'rating', 'ratedPlayers', 'progress', 'lastSeen', 'badges', 'weapons', 'map')) ?>
