<div class="markdown">
# User Handbook

Welcome to the **Lean Online Judge**! Here is a quick guide on how to get started reading and submitting formal proofs.

### 1. Find a Problem
Head over to the **[Problems](index.php?action=view_problems)** page. You will see a list of mathematical challenges translated into Lean 4 theorem signatures. Choose a problem by clicking on its title.

### 2. Understand the Statement
The problem page displays the informal mathematical statement along with the formal Lean `theorem` signature. Your goal is to write a Lean proof that successfully replaces the `sorry` and satisfies the theorem type.

### 3. Write Your Proof
You can provide your proof in two ways:
- **Direct Input:** Paste or type your Lean code directly into the online text editor provided on the problem page.
- **File Upload:** Upload a `.lean` file containing your solution. 

*Note: Make sure your submission includes all necessary `import` statements (like `import Mathlib`) if you rely on them.*

### 4. Submit and Wait for Judgment
Once you click submit, your code is placed into a queue. Our backend worker will compile your Lean code using the Lean 4 compiler.
- **PENDING / PROCESSING:** Your submission is waiting or is currently being verified.
- **PASSED:** Congratulations! Lean successfully verified your proof without any errors or sorries.
- **ERROR / TIMEOUT:** Your code failed to compile, contained an unresolved `sorry`, or took too long to verify. You can review the *Compiler Log* directly on your submission's detail page to see why it failed.

### 5. Local Reference Files
Some problems may depend on custom Lean files rather than standard Mathlib. These background theory files are listed in the **[Local Files](index.php?action=view_local_files)** section. If a problem references a local file, it will be automatically bundled when compiling your submissions. 

Happy proving!
</div>
