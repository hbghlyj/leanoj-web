<h2>How to use it?</h2>
<p>
  Some knowledge of Lean is necessary to solve problems on the platform. You can find installation instructions and resources to learn Lean on the <a target="_blank" href="https://lean-lang.org/">official website</a>. Going through the first few chapters of <a target="_blank" href="https://leanprover-community.github.io/mathematics_in_lean/">Mathematics in Lean </a> should be enough to get started.
</p>
<h3>How to submit a solution to a problem?</h3>
If you played with Lean for a bit, this should be relatively straight-forward.
<ul>
<li>
<p>
  <b>Problems that ask for a proof.</b><br>
  Replace <code>sorry</code> in the problem template with your proof and submit the resulting code.
</p>
<li>
<p>
<b>Problems that ask for an answer.</b><br>
  Some problems ask to provide an answer and prove that it's correct. Formally defining what an acceptable answer should be is difficult. Instead, valid answers are predefined and added to the <a href="index.php?action=view_answers">Answer Bank</a>. Replace the <code>answer</code> declaration in the problem template with one from the Answer Bank, then prove its correctness as usual.
</p>
<li>
<p>
  <b><a href="index.php?action=view_problem&id=25">xyzzy</a>.</b><br>
  This is a special problem that doesn't have a real template and can be used to submit a solution to any custom problem. It can also be used to test the checker against potential exploits (please, create an issue on <a target="_blank" href="https://github.com/Lean-Online-Judge/leanoj-checker/issues">GitHub</a> if you find one). Submissions are not visible to other participants but still visible to the admin.
</p>
</ul>
You can import Mathlib modules not present in the template but try to keep imports light to avoid timeout. The checker is using the latest stable version of Mathlib (currently, v4.28.0).
<h3>How to participate in a contest?</h3>
<p>
  Simply go to an ongoing contest and start solving problems! Contest problems are still available for upsolving after contest is over.
</p>
