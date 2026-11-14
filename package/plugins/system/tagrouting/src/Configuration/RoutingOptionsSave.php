<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\TagRouting\Configuration;

use Joomla\CMS\Table\Extension;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/** One native Tags save, two extension records, one transaction. No duplicated settings. */
final class RoutingOptionsSave
{
    private ?int $pending = null;

    public function __construct(private DatabaseInterface $db) {}

    public function before(string $context, object $subject): void
    {
        if ($context !== 'com_config.component' || ($subject->element ?? '') !== 'com_tags'
            || ($subject->type ?? '') !== 'component') { return; }
        $params = new Registry($subject->params ?? '');
        $values = $params->toArray(); $routing = [];
        foreach (RoutingOptions::DEFAULTS as $key => $default) {
            if (array_key_exists('smarttag_' . $key, $values)) {
                $routing[$key] = $values['smarttag_' . $key];
                unset($values['smarttag_' . $key]);
            }
        }
        if ($routing === []) { return; }
        $this->db->transactionStart(true);
        $this->pending = (int) $subject->extension_id;
        // Core can throw or another extension can veto after our before-save hook.
        // Roll back before connection teardown if the after-save event never arrives.
        register_shutdown_function(function (): void { try { $this->abort(); } catch (\Throwable) { /* Connection teardown will also roll back. */ } });
        try {
            $plugin = new Extension($this->db);
            if (!$plugin->load(['type'=>'plugin','folder'=>'system','element'=>'tagrouting'])) {
                throw new \RuntimeException('SmartTag Routing plugin record not found.');
            }
            $stored = new Registry($plugin->params);
            $routing = RoutingOptions::normalize($routing + ['custom_marker'=>$stored->get('custom_marker', '')]);
            foreach ($routing as $key => $value) { $stored->set($key, $value); }
            $markers = (array) $stored->get('accepted_markers', []);
            $stored->set('enable_custom_marker', in_array('__custom__', $markers, true) ? 1 : 0);
            $plugin->params = $stored->toString();
            if (!$plugin->store()) { throw new \RuntimeException('Could not save SmartTag Routing settings.'); }
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
