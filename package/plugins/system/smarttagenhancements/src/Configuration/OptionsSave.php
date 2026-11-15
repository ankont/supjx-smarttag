<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\SmartTagEnhancements\Configuration;

use Joomla\CMS\Table\Extension;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/** One native Tags save, two extension records, one transaction. No duplicated settings. */
final class OptionsSave
{
    private ?int $pending = null;

    public function __construct(private DatabaseInterface $db) {}

    public function before(string $context, object $subject): void
    {
        if ($context !== 'com_config.component' || ($subject->element ?? '') !== 'com_tags'
            || ($subject->type ?? '') !== 'component') { return; }
        $params = new Registry($subject->params ?? '');
        $values = $params->toArray(); $changes = [];
        foreach (EnhancementOptions::DEFAULTS as $key => $default) {
            if (array_key_exists('enhancements_' . $key, $values)) {
                $changes[$key] = $values['enhancements_' . $key];
                unset($values['enhancements_' . $key]);
            }
        }
        if ($changes === []) { return; }
        $this->db->transactionStart(true);
        $this->pending = (int) $subject->extension_id;
        // Core can throw or another extension can veto after our before-save hook.
        // Roll back before connection teardown if the after-save event never arrives.
        register_shutdown_function(function (): void { try { $this->abort(); } catch (\Throwable) { /* Connection teardown will also roll back. */ } });
        try {
            $plugin = new Extension($this->db);
            if (!$plugin->load(['type'=>'plugin','folder'=>'system','element'=>'smarttagenhancements'])) {
                throw new \RuntimeException('SmartTag Enhancements plugin record not found.');
            }
            $stored = new Registry($plugin->params);
            foreach ($changes as $key => $value) {
                if (!in_array($value, [0, 1, '0', '1'], true)) {
                    throw new \InvalidArgumentException('Invalid SmartTag Enhancements setting.');
                }
                $stored->set($key, (int) $value);
            }
            $plugin->params = $stored->toString();
            if (!$plugin->store()) { throw new \RuntimeException('Could not save SmartTag Enhancements settings.'); }
            // Only native com_tags params reach its extension record.
            $subject->params = (new Registry($values))->toString();
        } catch (\Throwable $error) {
            $this->abort();
            throw $error;
        }
    }

    public function after(string $context, object $subject): void
    {
        if ($context === 'com_config.component' && $this->pending !== null
            && $this->pending === (int) ($subject->extension_id ?? 0)) {
            try { $this->db->transactionCommit(true); $this->pending = null; }
            catch (\Throwable $error) { $this->abort(); throw $error; }
        }
    }

    public function abort(): void
    {
        if ($this->pending !== null) {
            $this->pending = null;
            $this->db->transactionRollback(true);
        }
    }
}
