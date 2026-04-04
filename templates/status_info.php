<h2>Submission Status</h2>
<p>
  Below is a brief explanation of possible submission statuses. For details, see the checker <a target="_blank" href="https://github.com/Lean-Online-Judge/leanoj-web/blob/master/worker.php">source code</a>.

</p>
<h3>Pending</h3>
<p>
  The submission has not been judged yet. You may need to refresh the page to see if the status has been updated.
</p>
<h3>System error</h3>
<p>
  Something went wrong on the system side. Please, contact the admin via <a target="_blank" href="https://discord.gg/a4xYPXXBxU">Discord</a>.
</p>
<h3>Compilation Error</h3>
</p>
  Submission compilation has failed. Make sure your <code>Mathlib</code> version matches the one used by the checker. It is normally the latest stable (currently, v4.29.0). You can check the version in your Lean editor with <code>#eval Lean.versionString</code>.
</p>
<h3>Time out</h3>
<p>
  Submission compilation took more time than the predefined limit. This might be caused by heavy imports (e.g., whole <code>Mathlib</code>). The <code>#min_imports</code> macro can be helpful in this case.
</p>
<h3>Out of memory</h3>
<p>
  Submission compilation took more memory than the predefined limit.
</p>
<h3>Dependency Mismatch</h3>
<p>
  The provided solution does not match one of the required dependencies for this problem. Make sure you are using declarations from the <a href="index.php?action=view_local_files">Dependency Library</a>.
</p>
<h3>Template mismatch</h3>
<p>
  Some declarations in the template are missing or do not match those in the submission. Extra declarations are allowed.
</p>
<h3>Rejected</h3>
<p>
  The submission has not passed extra verification. Make sure that <code>propext</code>, <code>Classical.choice</code>, and <code>Quot.sound</code> are the only axioms used. This can be checked with <code>#print axioms solution</code>. Note that some tactics, such as <code>native_decide</code>, may rely on extra axioms. The status can also be caused by attempts to trick the checker (e.g., by tampering the environment via metaprogramming). If that is your intention, there is a special <a href="index.php?action=view_problem&id=25">problem</a> for that.

</p>
<h3>Passed</h3>
<p>
  The submission has passed. Hooray!
</p>
