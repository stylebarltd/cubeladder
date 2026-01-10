
<div class="text-white">

    <div class="bg-zinc-800 rounded-xl shadow-lg p-4 text-white">
        <h3 class="text-lg font-bold mb-3 flex items-center gap-2">
            ℹ️ Rules & Info
        </h3>

        <ul class="space-y-2 text-sm text-zinc-300">
            <li class="flex gap-2">
                <span class="text-green-400">✔</span>
                <span>
                Only the <b>last 100 games</b> are counted.
                Games must start with a minimum of <b>4 players</b>.
            </span>
            </li>

            <li class="flex gap-2">
                <span class="text-green-400">✔</span>
                <span>
                <b>Do not change your name</b> during a game.
            </span>
            </li>

            <li class="flex gap-2">
                <span class="text-green-400">✔</span>
                <span>
                <b>Weekly achievements</b> are awarded every <b>Sunday</b>.
            </span>
            </li>

            <li class="flex gap-2">
                <span class="text-yellow-400">⚠</span>
                <span>
                Write us a message if you <b>do not want to be tracked</b>.
            </span>
            </li>


            <li class="flex gap-2">
                <span class="text-pink-400">♥</span>
                <span>
                The world would be better if we were all
                <a
                    href="https://zz.ketar.eu/in-loving-memory-of-elias-armed/"
                    class="underline italic hover:text-blue-400"
                    target="_blank"
                >
                    <u>Armed</u></a>.
            </span>
            </li>
        </ul>
    </div>

    <div class="overflow-x-auto bg-zinc-800 rounded-xl shadow-lg p-4">
        <table class="min-w-full text-sm text-white border border-zinc-700 rounded-lg">
            <thead class="bg-zinc-900 text-zinc-300">
            <tr>
                <th class="px-3 py-2 text-left">Event</th>
                <th class="px-3 py-2 text-center">Points</th>
                <th class="px-3 py-2 text-left">Description</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-zinc-700">
            <!-- Standard kills -->
            <tr class="bg-zinc-800/80">
                <td class="px-3 py-2 font-mono">kills</td>
                <td class="px-3 py-2 text-center font-bold text-green-400">+1</td>
                <td class="px-3 py-2">Any kill</td>
            </tr>

            <!-- Weapon kills -->
            <tr>
                <td class="px-3 py-2 font-mono">busted</td>
                <td class="px-3 py-2 text-center font-bold text-green-400">+1</td>
                <td class="px-3 py-2">Pistol kill</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">shredded</td>
                <td class="px-3 py-2 text-center font-bold text-green-400">+1</td>
                <td class="px-3 py-2">Rifle kill</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">sprayed</td>
                <td class="px-3 py-2 text-center font-bold text-green-400">+1</td>
                <td class="px-3 py-2">SMG kill</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">punctured</td>
                <td class="px-3 py-2 text-center font-bold text-green-400">+1</td>
                <td class="px-3 py-2">Sniper kill</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">splattered</td>
                <td class="px-3 py-2 text-center font-bold text-green-400">+1</td>
                <td class="px-3 py-2">Shotgun kill</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">peppered</td>
                <td class="px-3 py-2 text-center font-bold text-green-400">+1</td>
                <td class="px-3 py-2">Shotgun kill</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">picked_off</td>
                <td class="px-3 py-2 text-center font-bold text-green-400">+1</td>
                <td class="px-3 py-2">Carbine kill</td>
            </tr>

            <!-- Skill kills -->
            <tr class="bg-zinc-800/80">
                <td class="px-3 py-2 font-mono">headshot</td>
                <td class="px-3 py-2 text-center font-bold text-blue-400">+2</td>
                <td class="px-3 py-2">Precision headshot</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">slashed</td>
                <td class="px-3 py-2 text-center font-bold text-blue-400">+2</td>
                <td class="px-3 py-2">Knife kill</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">gibbed</td>
                <td class="px-3 py-2 text-center font-bold text-blue-400">+2</td>
                <td class="px-3 py-2">Grenade kill</td>
            </tr>

            <!-- Objective play -->
            <tr class="bg-zinc-800/80">
                <td class="px-3 py-2 font-mono">stole_the_flag</td>
                <td class="px-3 py-2 text-center font-bold text-emerald-400">+2</td>
                <td class="px-3 py-2">Flag stolen</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">returned_the_flag</td>
                <td class="px-3 py-2 text-center font-bold text-emerald-400">+2</td>
                <td class="px-3 py-2">Flag returned</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">scored_with_the_flag</td>
                <td class="px-3 py-2 text-center font-bold text-yellow-400">+5</td>
                <td class="px-3 py-2">Flag captured</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">lost_the_flag</td>
                <td class="px-3 py-2 text-center font-bold text-red-400">-1</td>
                <td class="px-3 py-2">Flag lost</td>
            </tr>

            <!-- Penalties -->
            <tr class="bg-zinc-800/80">
                <td class="px-3 py-2 font-mono">teamkills</td>
                <td class="px-3 py-2 text-center font-bold text-red-400">-1</td>
                <td class="px-3 py-2">Team kill penalty</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">suicided</td>
                <td class="px-3 py-2 text-center font-bold text-red-400">-1</td>
                <td class="px-3 py-2">Suicide</td>
            </tr>
            </tbody>
        </table>
    </div>





</div>

