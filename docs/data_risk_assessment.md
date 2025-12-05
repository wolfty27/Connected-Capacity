
# ⚖️ Long-Form Risk Assessment  
### CLOUD Act • PHIPA • PIPEDA

---

# 1. CLOUD Act
U.S. CLOUD Act applies to Google/AWS/Azure even in Canadian regions.

### Mitigations
- No PHI stored on U.S.-controlled infrastructure  
- Only de-identified/tokenized data leaves PHI layer  
- PHI hosted in Azure Canada or sovereign ThinkOn  

Residual risk: **Low**

---

# 2. PHIPA Assessment
Requirement | Status  
--- | ---  
Custodial control | ✔  
Encryption | ✔  
Residency (Canada) | ✔  
Audit trails | ✔  
No foreign transfer of PHI | ✔  

---

# 3. PIPEDA Assessment
- Allows non-identifiable data to be processed outside Canada  
- Contractual controls remain required  

Residual risk: **Low**

---

# 4. Threat Model
Threat | Control  
--- | ---  
Token leakage | Vault-backed tokenization  
Misconfigured IAM | Zero-trust + RBAC  
Side-channel inference | Embedding privacy filters  

Residual risk: **Low to Moderate**
