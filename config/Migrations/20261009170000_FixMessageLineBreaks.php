<?php
use Migrations\BaseMigration;

class FixMessageLineBreaks extends BaseMigration
{
    /**
     * Weekly achievement messages were written with a literal "\n" (single-quoted
     * PHP string) instead of a line break - turn them into real line breaks.
     */
    public function up(): void
    {
        $this->execute("UPDATE messages SET body = REPLACE(body, '\\\\n', '\\n') WHERE body LIKE '%\\\\\\\\n%'");
    }

    public function down(): void
    {
        // not reverted - the literal "\n" was a bug
    }
}
