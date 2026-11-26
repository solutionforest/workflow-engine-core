# Upgrading

## From v2 to v3

Most code keeps working. Check these:

1. **Delays.** `DelayAction` / `WorkflowBuilder::delay()` no longer sleep. The workflow ends the
   call in `WAITING`; call `$engine->resumeDue()` regularly (a cron job or scheduler, every minute)
   to continue workflows whose delay has passed. With workflow-engine-laravel v2 the
   `workflow:resume-due` command is scheduled for you.
2. **Timeouts.** Step timeouts (`timeout`, `#[Timeout]`) are no longer enforced with SIGALRM.
   Enforce them in your queue worker, or, in a CLI worker that runs one workflow at a time:

   ```php
   $engine = new WorkflowEngine($storage, $events, $logger, signalTimeouts: true);
   ```
3. **Your own `StorageAdapter`.** `save()` must be a compare-and-swap on the revision:

   ```php
   public function save(WorkflowInstance $instance): void
   {
       $expected = $instance->getRevision();           // 0: never saved
       // write only if the stored revision is $expected (UPDATE ... WHERE revision = ?),
       // otherwise: throw new ConcurrentModificationException($id, $expected, $stored);
       // store $instance->toArray() (it now includes `wait` and `revision`) and the definition
       $instance->setRevision($expected + 1);
   }
   ```

   `findInstances()` should support `due_before` (WAITING instances whose
   `getWait()->resumeAt` is at or before it). `InMemoryStorage` is a reference implementation.
4. **Conflicts.** Code that saves instances can now get `ConcurrentModificationException`. Load the
   instance again and repeat; `signal()` and `resumeDue()` are safe to repeat.
5. **Suspended instances.** `resume()` throws for an instance waiting for a signal; deliver it with
   `signal()`. `ActionResult::isFailure()` is false for a suspended result.
6. **In-memory tests.** `InMemoryStorage::load()` returns a new copy each time; do not rely on
   getting the same object back.
