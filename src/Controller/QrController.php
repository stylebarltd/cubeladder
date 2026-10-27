<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\PlayerLink;
use App\Service\QrHunt;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Cookie\SameSiteEnum;

/**
 * /q/<token>: a QR code hidden in one of our maps (QrHunt). A recognised
 * player gets the find and their level; anyone else gets the chat code
 * (PlayerLink, as on /play): say it in a game, the page waits, and once the
 * browser is linked it reloads and the find counts.
 */
class QrController extends AppController
{
    public function find(string $token)
    {
        $hunt = new QrHunt();
        $code = $hunt->code($token);
        $player = $this->request->getAttribute('identity');
        $result = null;
        $linkCode = null;

        if ($code && $code['active'] && $player) {
            $result = $hunt->record((int)$code['id'], (string)$player->id);
            $result['level'] = $hunt->level((string)$player->id);
            $result['name'] = QrHunt::levelName($result['level']);
        } elseif ($code && $code['active']) {
            // not recognised: the chat code, kept in the same cookie as /play's
            $link = new PlayerLink();
            $linkCode = $this->request->getCookie('link_code');
            if (!$link->open($linkCode)) {
                $linkCode = $link->issue();
                $this->response = $this->response->withCookie((new Cookie('link_code', $linkCode))
                    ->withExpiry(new \DateTime('+' . PlayerLink::VALID_HOURS . ' hours'))->withPath('/')
                    ->withSecure($this->request->is('https'))->withHttpOnly(true)->withSameSite(SameSiteEnum::LAX));
            }
        }

        $this->set([
            'code' => $code,
            'result' => $result,
            'linkCode' => $linkCode,
            'total' => $hunt->total(),
            'hunters' => $hunt->hunters(),
        ]);
        // a scanned code is never cached or indexed
        $this->response = $this->response->withHeader('Cache-Control', 'no-store')->withHeader('X-Robots-Tag', 'noindex');
    }
}
