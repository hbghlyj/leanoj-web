<style>
  .guide-container {
    display: flex;
    gap: 40px;
    margin-top: 20px;
  }
  .guide-column {
    flex: 1;
  }
  .guide-column h2 {
    border-bottom: 2px solid var(--primary);
    padding-bottom: 10px;
    margin-bottom: 20px;
  }
</style>

<div class="markdown">
# Guide

Welcome to the **Lean Online Judge**! This guide is split into two sections to help you either solve existing problems or contribute new ones to our library.

<div class="guide-container">
<div class="guide-column">
## For Solvers

### 1. Find a Problem
Head over to the **[Problems](index.php?action=view_problems)** page. You will see a list of mathematical challenges translated into Lean 4 theorem signatures. Choose a problem by clicking on its title.

### 2. Understand the Statement
The problem page displays the informal mathematical statement along with the formal Lean `theorem` signature. Your goal is to write a Lean proof that successfully replaces the `sorry` and satisfies the theorem type.

### 3. Write Your Proof
You can provide your proof in two ways:
- **Direct Input:** Paste or type your Lean code directly into the online text editor provided on the problem page.
- **File Upload:** Upload a `.lean` file containing your solution. 

**Tip:** You can use the **[Lean 4 Web Editor](https://live.lean-lang.org/)**, **[AXLE Verify](https://axle.axiommath.ai/verify_proof)**, or **[AXLE Check](https://axle.axiommath.ai/check)** to draft and verify your proof in real-time.

### 4. Instant Judgment & Archiving
Lean OJ uses **Instant Verification** powered by the AXLE API. When you click submit:
- **PASSED:** Congratulations! Lean successfully verified your proof. Your solution is permanently archived in the system.
- **ERROR:** Your code failed to verify. The compiler log will be displayed to you **immediately** on the problem page. 

*Note: Only successful proofs are stored in the database.*
</div>

<div class="guide-column">
## For Contributors

### 5. Recursive Dependencies & Libraries
Lean OJ supports **recursive problem dependencies**:
- If Problem B depends on Problem A, anything that depends on B will automatically inherit the theorems from A.
- These dependencies are automatically prepended to your proof context before verification.
- You can build complex mathematical libraries simply by creating problems and linking them together!

### 6. Open Contribution & Permissions
Lean OJ is a collaborative platform designed for growth:
- **Add Problems:** Any registered user can create a new problem.
- **Edit Everything:** Any registered user can edit the title, statement, or template of *any* existing problem.
- **Version History:** Every edit is recorded as a new **Revision**. You can view the history and compare versions by clicking the **[History]** link on any problem page.

*Note: Mathlib is imported automatically and available globally.*
</div>
</div>

<br>
Happy proving!
</div>