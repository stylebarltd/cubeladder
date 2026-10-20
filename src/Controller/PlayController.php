<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AimTrainer;
use App\View\Helper\LayoutHelper;
use Cake\Event\EventInterface;

/**
 * Aim trainer: /play (the game and the boards), /play/start and
 * /play/finish (JSON, one round each; see AimTrainer).
 */
class PlayController extends AppController
{
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        // JSON posts from the game (CSRF token in the header still applies)
        $this->FormProtection->setConfig('unlockedActions', ['start', 'finish']);
    }

    public function index()
    {
        $trainer = new AimTrainer();
        $week = $trainer->board(AimTrainer::weekStart());
        $allTime = $trainer->board(null);
        // map screenshots as backgrounds, a different one per round
        $files = array_map('basename', glob(WWW_ROOT . 'img/maps/*.jpg') ?: []);
        shuffle($files);
        $maps = array_map(fn($f) => ['name' => basename($f, '.jpg'), 'url' => LayoutHelper::mapUrl($f)], array_slice($files, 0, 12));
        $this->set(compact('week', 'allTime', 'maps'));
    }

    public function start()
    {
        $this->request->allowMethod(['post']);
        $data = (new AimTrainer())->start($this->request->getAttribute('identity')?->id);

        return $this->json($data);
    }

    public function finish()
    {
        $this->request->allowMethod(['post']);
        $body = (array)$this->request->getData();
        $result = (new AimTrainer())->finish(
            (string)($body['token'] ?? ''),
            (string)($body['events'] ?? ''),
            is_array($body['reactions'] ?? null) ? $body['reactions'] : [],
        );
        if (isset($result['error'])) {
            return $this->json($result, 422);
        }
        $result['week'] = (new AimTrainer())->board(AimTrainer::weekStart());

        return $this->json($result);
    }

    private function json(array $data, int $status = 200)
    {
        return $this->response->withStatus($status)->withType('application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withStringBody((string)json_encode($data));
    }
}
