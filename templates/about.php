<div class="markdown">
# Guide

Welcome to the **Lean Online Judge**! Here is a quick guide on how to get started reading and submitting formal proofs.

### 1. Find a Problem
Head over to the **[Problems](index.php?action=view_problems)** page. You will see a list of mathematical challenges translated into Lean 4 theorem signatures. Choose a problem by clicking on its title.

### 2. Understand the Statement
The problem page displays the informal mathematical statement along with the formal Lean `theorem` signature. Your goal is to write a Lean proof that successfully replaces the `sorry` and satisfies the theorem type.

### 3. Write Your Proof
You can provide your proof in two ways:
- **Direct Input:** Paste or type your Lean code directly into the online text editor provided on the problem page.
- **File Upload:** Upload a `.lean` file containing your solution. 

*Note: Mathlib is the only external library available and is imported automatically. For any other code dependencies, you must first create those theorems as separate **Problems** in Lean OJ and then select them via the **Dependencies** select field in the problem settings.*

**Tip:** You can use the **[Lean 4 Web Editor](https://live.lean-lang.org/)**, **[AXLE Verify](https://axle.axiommath.ai/verify_proof)**, or **[AXLE Check](https://axle.axiommath.ai/check)** to draft and verify your proof in real-time. You can also use **[AXLE Simplify](https://axle.axiommath.ai/simplify_theorems)** to optimize your theorems before submitting.

### 4. Instant Judgment & Archiving
Lean OJ uses **Instant Verification** powered by the AXLE API. When you click submit:
- **PASSED:** Congratulations! Lean successfully verified your proof. Your solution is permanently archived in the system and displayed in the submissions list.
- **ERROR:** Your code failed to verify (e.g., it contains an unresolved `sorry` or a logic error). The compiler log will be displayed to you **immediately** on the problem page. 

*Note: In our "Success-Only" model, only successful proofs are stored. Unsuccessful attempts are shown to you for real-time debugging but are not added to the database.*

### 5. Recursive Dependencies & Libraries
Lean OJ supports **recursive problem dependencies**:
- If Problem B depends on Problem A, anything that depends on B will automatically inherit the theorems from A.
- These dependencies are automatically prepended to your proof context before verification.
- You can build complex mathematical libraries simply by creating problems and linking them together!

### 6. Open Contribution & Permissions
Lean OJ is a collaborative platform:
- **Add Problems:** Any registered user can create a new problem.
- **Edit Everything:** Any registered user can edit the title, statement, or template of *any* existing problem.
- **Version History:** Every edit is recorded as a new **Revision**. You can view the history and compare versions by clicking the **[History]** link on any problem page.

Happy proving!
</div>