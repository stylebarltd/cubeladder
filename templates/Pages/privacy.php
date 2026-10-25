<?php
/**
 * Privacy policy (/privacy). Based on resources/cubeLadder — Privacy compliance
 * pack.pdf, Part 2, filled in with what the code really does (2026-10-25).
 * Keep it true: when the site changes how it uses data, change this page and
 * the date.
 *
 * Ladder.privacy.email (app_local.php): the privacy contact address; without
 * it the page points to the message form.
 *
 * @var \App\View\AppView $this
 */
use Cake\Core\Configure;

$this->assign('title', 'Privacy');
$email = Configure::read('Ladder.privacy.email');
$euRep = Configure::read('Ladder.privacy.euRepresentative');
$contactLink = $this->Html->link('the message form', ['controller' => 'Messages', 'action' => 'sendToAdmin'], ['class' => 'underline hover:text-white']);
$contact = $email
    ? '<a href="mailto:' . h($email) . '" class="underline hover:text-white">' . h($email) . '</a>'
    : $contactLink;
$servers = array_values(array_map(fn($s) => $s['name'], array_filter(
    (array)Configure::read('Ladder.servers'),
    fn($s) => ($s['live'] ?? true) !== false
)));
$h2 = 'mt-10 mb-3 text-xl font-bold text-white scroll-mt-24';
$table = 'w-full text-left text-sm';
$th = 'border-b border-white/15 py-2 pr-4 font-semibold text-zinc-300 align-top';
$td = 'border-b border-white/10 py-2 pr-4 align-top';
?>
<div class="mx-auto w-full max-w-4xl px-4 py-10 md:px-8 text-zinc-200 leading-relaxed">
    <div class="rounded-xl border border-white/15 bg-black/70 p-6 md:p-10 backdrop-blur-[2px]">
        <h1 class="text-3xl md:text-4xl font-extrabold text-white"><i class="fa-solid fa-shield-halved mr-2 text-green-400"></i>Privacy policy</h1>
        <p class="mt-2 text-sm text-zinc-400">Last updated: 25 October 2026</p>

        <p class="mt-6">cubeLadder (cubeladder.ovh) publishes rankings, statistics and live views of AssaultCube games played on the servers listed on the site. This page explains which personal data we process, why, and what you can do about it.</p>

        <h2 id="controller" class="<?= $h2 ?>">Who is responsible</h2>
        <p>The data controller is <b>pola|ZZ</b>, a private individual living in Thailand, who runs cubeLadder as a non-commercial hobby project.</p>
        <ul class="mt-3 list-disc pl-6 space-y-1">
            <li>Privacy contact: <?= $contact ?></li>
            <?php if ($euRep): ?><li>EU representative: <?= h($euRep) ?></li><?php endif; ?>
        </ul>
        <p class="mt-3">The domain cubeladder.ovh is registered by ZZ|Ketar* as a sponsor. He does not run the site or decide how data is used. Please send privacy requests to the contact above.</p>

        <h2 id="sources" class="<?= $h2 ?>">Where the data comes from</h2>
        <p>Most data comes from the logs of the AssaultCube game servers on the ladder (<?= h(implode(', ', $servers)) ?>), which their operators share with cubeLadder, and from the live status of those servers. The rest comes from you when you use the website.</p>

        <h2 id="data" class="<?= $h2 ?>">What we process and why</h2>
        <div class="overflow-x-auto">
        <table class="<?= $table ?>">
            <thead><tr><th class="<?= $th ?>">Data</th><th class="<?= $th ?>">Purpose</th><th class="<?= $th ?>">Legal basis</th><th class="<?= $th ?>">Kept for</th></tr></thead>
            <tbody>
            <tr><td class="<?= $td ?>">Nickname and AssaultCube public key</td><td class="<?= $td ?>">Recognise you across games and name changes</td><td class="<?= $td ?>">Legitimate interest</td><td class="<?= $td ?>">While the ladder runs</td></tr>
            <tr><td class="<?= $td ?>">Game events: kills, flags, scores, teams, minutes, accuracy, damage, ping, votes</td><td class="<?= $td ?>">Rankings, ratings, Hall of Fame, achievements, live view</td><td class="<?= $td ?>">Legitimate interest</td><td class="<?= $td ?>">While the ladder runs</td></tr>
            <tr><td class="<?= $td ?>">In-game chat lines (full text)</td><td class="<?= $td ?>">Live view of a running game; counts such as "said gg 120 times" on player pages. The text itself is not shown on player pages.</td><td class="<?= $td ?>">Legitimate interest</td><td class="<?= $td ?>">While the ladder runs</td></tr>
            <tr><td class="<?= $td ?>">Country and approximate location</td><td class="<?= $td ?>">Country flags, rankings by country, player map. Worked out from your IP address with a local GeoLite2 database (no outside service is asked); map positions are shifted at random by up to ~100 km.</td><td class="<?= $td ?>">Legitimate interest</td><td class="<?= $td ?>">While the ladder runs</td></tr>
            <tr><td class="<?= $td ?>">IP address</td><td class="<?= $td ?>">Recognise you on the website without an account (your name at the top right, "Edit profile", messages, "Don't track me"), and work out your country</td><td class="<?= $td ?>">Legitimate interest</td><td class="<?= $td ?>">Only the latest one per profile, cleared 90 days after your last game. Raw server logs, which contain IP addresses, are deleted 30 days after import.</td></tr>
            <tr><td class="<?= $td ?>">Profile choices (picture, "Don't track me")</td><td class="<?= $td ?>">Your settings</td><td class="<?= $td ?>">Providing the service you use</td><td class="<?= $td ?>">While your profile exists</td></tr>
            <tr><td class="<?= $td ?>">Messages (inbox, messages to the admin)</td><td class="<?= $td ?>">Answer you and send achievement notices</td><td class="<?= $td ?>">Providing the service you use</td><td class="<?= $td ?>">Until you delete them in your inbox</td></tr>
            <tr><td class="<?= $td ?>">Aim trainer rounds</td><td class="<?= $td ?>">Aim trainer boards and records</td><td class="<?= $td ?>">Providing the service you use</td><td class="<?= $td ?>">While the boards run</td></tr>
            <tr><td class="<?= $td ?>">Pages visited and referrer</td><td class="<?= $td ?>">Count visits</td><td class="<?= $td ?>">Legitimate interest</td><td class="<?= $td ?>">Counts per day only, kept about 13 months. Guests are counted by a hash of IP address and browser with a random key that is replaced every day, so no IP address is stored and a guest cannot be followed from one day to the next.</td></tr>
            </tbody>
        </table>
        </div>
        <p class="mt-4">Our legitimate interest is to run a fair, public ranking for the AssaultCube community. Legal bases: GDPR Art. 6(1)(f) and Thai PDPA Section 24(5) for legitimate interest; GDPR Art. 6(1)(b) and PDPA Section 24(3) for providing the service you use.</p>
        <p class="mt-3">We use your data to build ratings, player types and similar labels from your game statistics. These are for fun and community rankings only and have no legal or similar effect on you.</p>

        <h2 id="public" class="<?= $h2 ?>">What is public</h2>
        <p>Your nickname, country flag, game statistics, ratings, records and achievements are shown publicly on the site and shared in our Discord server. Chat lines of a game that is running right now appear in its live view. Your IP address, your public key and your messages are never published.</p>

        <h2 id="recipients" class="<?= $h2 ?>">Who else receives data</h2>
        <ul class="list-disc pl-6 space-y-2">
            <li><b>Hetzner Online GmbH</b> (Germany) hosts the website and its database, under a data processing agreement.</li>
            <li><b>OVH</b> (France) runs the domain's DNS.</li>
            <li id="discord" class="scroll-mt-24"><b>Discord Inc.</b> (USA): our bot posts public game results, records and achievements to the cubeLadder Discord server. If you click "Show the Discord widget" on the About page, discord.com receives your IP address and Discord's own privacy policy applies. The Discord avatars on the front page are copied to our server first, so showing them sends nothing to Discord.</li>
            <li id="map" class="scroll-mt-24"><b>OpenStreetMap</b> (OpenStreetMap Foundation, UK): if you click "Show the map background" on the player map, the map pictures are loaded from openstreetmap.org, which receives your IP address.</li>
            <li><b>Game server operators</b> share their server logs with us. They run their servers under their own rules.</li>
        </ul>
        <p class="mt-3">Everything else the pages need (fonts, icons, scripts) is served from cubeladder.ovh itself. We do not sell data and do not use it for advertising.</p>

        <h2 id="storage" class="<?= $h2 ?>">Where the data is stored</h2>
        <p>The site's data is stored in Germany. The controller manages it from Thailand, and public results also appear on Discord, whose servers are in the USA.</p>

        <h2 id="cookies" class="<?= $h2 ?>">Cookies and storage in your browser</h2>
        <p>We use only cookies that the site needs to work. We use no analytics, advertising or tracking cookies, so we do not ask for consent.</p>
        <div class="mt-3 overflow-x-auto">
        <table class="<?= $table ?>">
            <thead><tr><th class="<?= $th ?>">Name</th><th class="<?= $th ?>">Purpose</th><th class="<?= $th ?>">Lifetime</th></tr></thead>
            <tbody>
            <tr><td class="<?= $td ?> font-mono">csrfToken</td><td class="<?= $td ?>">Protects forms against forged requests</td><td class="<?= $td ?>">End of the browser session</td></tr>
            <tr><td class="<?= $td ?> font-mono">selected_player_id</td><td class="<?= $td ?>">Remembers which player you are once we recognised you by the connection you play from (encrypted, only set for players)</td><td class="<?= $td ?>">30 days</td></tr>
            <tr><td class="<?= $td ?> font-mono">CAKEPHP</td><td class="<?= $td ?>">Session: short notices after saving a form, the admin login</td><td class="<?= $td ?>">End of the browser session</td></tr>
            </tbody>
        </table>
        </div>
        <p class="mt-4">The site also keeps a few settings in your browser's local storage. They never leave your device:</p>
        <ul class="mt-2 list-disc pl-6 space-y-1">
            <li><span class="font-mono">privacyNotice</span>: you closed the privacy notice.</li>
            <li><span class="font-mono">external.discord</span>, <span class="font-mono">external.osm</span>: you chose to always load the Discord widget or the map background.</li>
            <li><span class="font-mono">aimSound</span>: sound on or off in the aim trainer.</li>
        </ul>
        <p class="mt-3">To undo them, clear this site's data in your browser.</p>

        <h2 id="rights" class="<?= $h2 ?>">Your rights</h2>
        <p>You can ask us to:</p>
        <ul class="mt-2 list-disc pl-6 space-y-1">
            <li>show you the data we hold about you and give you a copy;</li>
            <li>correct data that is wrong;</li>
            <li>delete or anonymise your data;</li>
            <li>restrict how we use it;</li>
            <li>stop tracking you (right to object).</li>
        </ul>
        <p class="mt-3">The quickest way to stop tracking is <b>Don't track me</b>: open your player page, choose Edit profile, then Don't track me, from the connection you play from. Your profile then disappears from the rankings, the Hall of Fame, achievements and your public player page, past games included. If you want your history deleted or anonymised as well, write to us.</p>
        <p class="mt-3">For anything else, write to <?= $contact ?><?= $email ? ' or use ' . $contactLink : '' ?>. We answer within 30 days and may ask you to confirm who you are first, for example by writing from the connection you play from.</p>
        <p class="mt-3">You can also complain to a data protection authority: in the EU, the authority of the country where you live or play; in Thailand, the Personal Data Protection Committee (PDPC).</p>

        <h2 id="young" class="<?= $h2 ?>">Younger players</h2>
        <p>cubeLadder is open to all AssaultCube players. If you are under 18, or a parent of a younger player, and want your data removed, write to us and we will delete or anonymise it quickly.</p>

        <h2 id="security" class="<?= $h2 ?>">Security</h2>
        <p>The site uses HTTPS. Access to the server and the admin area is protected and limited to the site owner.</p>

        <h2 id="changes" class="<?= $h2 ?>">Changes</h2>
        <p>We will update this page when the site changes how it uses data. The date at the top shows the latest version.</p>
    </div>
</div>
