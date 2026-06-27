# Critérios de Consolidação de Vínculos (CNAE x CBO)

Esta documentação analisa os critérios utilizados na view materializada `mvw_matriz_classificacao_conciliada_vinculos` para consolidar a classificação de exposição cruzando dados de **Atividade Econômica (CNAE)** e **Ocupação (CBO)**.

Cada critério (de 1 a 10) define uma lógica específica de combinação (matriz 3x3) onde:
* **0**: Não exposto (NEX)
* **1**: Condicionalmente exposto (CEX)
* **2**: Exposto (EXP)
* **9**: Não aplicável / Não classificado (NCL)

---

### Critério 1: Interseção Estrita
*Só há exposição se ambas as classificações forem totalmente expostas.*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 0 |
| **Condicionalmente (1)** | 0 | 0 | 0 |
| **Exposto (2)** | 0 | 0 | 2 |

---

### Critério 2: Prevalência do Exposto
*Só é exposto se ao menos um for exposto e o outro não for "Não exposto".*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 0 |
| **Condicionalmente (1)** | 0 | 0 | 2 |
| **Exposto (2)** | 0 | 2 | 2 |

---

### Critério 3: Condição de Nível Intermediário
*Semelhante ao Critério 2, mas preserva a condição intermediária se ambos forem condicionalmente expostos.*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 0 |
| **Condicionalmente (1)** | 0 | 1 | 2 |
| **Exposto (2)** | 0 | 2 | 2 |

---

### Critério 4: Exposição Atenuada por Oposição
*Classificações opostas (0 e 2) resultam em classificação atenuada (1).*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 1 |
| **Condicionalmente (1)** | 0 | 1 | 2 |
| **Exposto (2)** | 1 | 2 | 2 |

---

### Critério 5: Prevalência de Exposto Direto
*Se qualquer um for exposto (2), o resultado é exposto (2), exceto se o outro for 0 ou 1.*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 2 |
| **Condicionalmente (1)** | 0 | 1 | 2 |
| **Exposto (2)** | 2 | 2 | 2 |

---

### Critério 6: Prevalência Máxima (União)
*Retorna o maior nível de exposição presente entre as duas classificações.*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 1 | 2 |
| **Condicionalmente (1)** | 1 | 1 | 2 |
| **Exposto (2)** | 2 | 2 | 2 |

---

### Critério 7: Proteção Estrita (Foco no Não Exposto)
*Se qualquer um for não exposto (0), o resultado é 0, a menos que o outro seja totalmente exposto (2).*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 2 |
| **Condicionalmente (1)** | 0 | 1 | 2 |
| **Exposto (2)** | 0 | 2 | 2 |

---

### Critério 8: Proteção Atenuada
*Semelhante ao Critério 7, mas se uma das partes for Não Exposto (0) e a outra Exposto (2), o resultado é atenuado para Condicionalmente Exposto (1).*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 1 |
| **Condicionalmente (1)** | 0 | 1 | 2 |
| **Exposto (2)** | 0 | 2 | 2 |

---

### Critério 9: Interseção Qualificada
*Nenhuma exposição se houver Não Exposto (0). Se ambos forem ao menos condicionalmente expostos, o resultado é totalmente exposto (2).*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 0 |
| **Condicionalmente (1)** | 0 | 2 | 2 |
| **Exposto (2)** | 0 | 2 | 2 |

---

### Critério 10: Interseção Gradual (Média Lógica)
*Nenhuma exposição se houver Não Exposto (0). Caso contrário, segue um crescimento gradual da exposição.*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 0 |
| **Condicionalmente (1)** | 0 | 1 | 1 |
| **Exposto (2)** | 0 | 1 | 2 |
