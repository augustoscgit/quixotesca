# Carex-BR: metodologia, matrizes ocupacionais e estimativas de exposição

**Documento técnico para usuários logados e especialistas do sistema Carex-BR**  
**Versão de trabalho:** 2026-06-05  
**Escopo:** descrição metodológica do projeto Carex-BR, do processo de construção das matrizes de exposição ocupacional e dos critérios utilizados para classificação, consolidação e estimativa de trabalhadores expostos.

---

## 1. Finalidade deste documento

Este documento apresenta a base metodológica do **Carex-BR** para especialistas, revisores e usuários logados do sistema. Seu objetivo é tornar explícitos os conceitos, decisões metodológicas, etapas operacionais e critérios técnicos utilizados na construção das matrizes brasileiras de exposição ocupacional a agentes cancerígenos.

Diferentemente de uma apresentação voltada ao público geral, este texto assume familiaridade mínima com epidemiologia ocupacional, vigilância em saúde do trabalhador, classificações ocupacionais e econômicas, e uso de bases populacionais ou administrativas. A ênfase recai sobre a rastreabilidade das decisões: como os agentes são priorizados, como as categorias CNAE e CBO são avaliadas, como as classificações são herdadas ou refinadas, como os pares ocupação--atividade econômica são consolidados e como se obtêm estimativas de trabalhadores expostos.

---

## 2. O que é o CAREX e qual é o papel do Carex-BR

O **CAREX** (*CARcinogen EXposure*) é uma metodologia e um sistema de informação de base populacional para estimar a exposição ocupacional a agentes cancerígenos. Sua origem está vinculada ao Finnish Institute of Occupational Health e à International Agency for Research on Cancer, a partir da experiência da matriz finlandesa de exposição ocupacional, a **FINJEM**. Em sua formulação internacional, o CAREX foi utilizado para estimar a proporção de trabalhadores expostos a carcinógenos em países europeus, tornando-se uma referência para vigilância de exposições ocupacionais em escala populacional.

O **Carex-BR** adapta essa tradição metodológica ao contexto brasileiro. O projeto considera que a transferência direta de estimativas internacionais para o Brasil pode ser inadequada quando há diferenças relevantes nos processos produtivos, no padrão tecnológico, nas relações de trabalho, na informalidade, na composição setorial e nas classificações utilizadas nas fontes de dados nacionais. Por isso, o Carex-BR busca conciliar a lógica internacional das matrizes de exposição ocupacional com fontes, classificações e conhecimento técnico-científico sobre a realidade brasileira.

No Brasil, o projeto foi organizado a partir de grupos de especialistas e teve como agentes prioritários iniciais:

- benzeno;
- asbesto/amianto;
- sílica;
- radiação ionizante;
- agrotóxicos.

A metodologia descrita aqui foi desenvolvida inicialmente a partir das matrizes de **benzeno** e **sílica**, sendo posteriormente utilizada também para o **amianto**. Cada agente pode exigir ajustes específicos, mas a arquitetura geral do método é comum: seleção do agente, identificação da força de trabalho potencialmente exposta, classificação técnica das categorias ocupacionais e econômicas, consolidação dos vínculos e estimativa da população exposta.

---

## 3. Princípios gerais do método

O Carex-BR segue três passos gerais, compatíveis com o guia técnico para projetos CAREX na América Latina e Caribe:

1. **Definir agentes cancerígenos prioritários.**
2. **Identificar a população trabalhadora de referência.**
3. **Determinar a prevalência ou a frequência esperada de exposição.**

No entanto, a aplicação desses passos no Brasil exige escolhas metodológicas próprias. Em países com bases nacionais robustas de medições ambientais, a prevalência e o nível de exposição podem ser estimados a partir de bancos de medidas ocupacionais. No Brasil, a escassez de medições sistemáticas e padronizadas em escala nacional torna necessário combinar diversas fontes de evidência, incluindo literatura técnica e científica, informações setoriais, conhecimento acumulado por especialistas e registros administrativos sobre a força de trabalho.

Assim, o Carex-BR não parte, em sua metodologia básica, de probabilidades de exposição previamente calculadas em outros países. A estratégia central é a **classificação qualitativa ou semiqualitativa de ocupações e atividades econômicas por especialistas nacionais**, considerando as características médias esperadas das categorias avaliadas.

Essa opção favorece maior aderência às condições brasileiras, mas exige documentação explícita dos critérios, pois envolve julgamento técnico e pode ser influenciada por incerteza, heterogeneidade interna das categorias, disponibilidade desigual de evidências e diferenças entre agentes.

---

## 4. Fontes de dados sobre a força de trabalho

A estimativa de trabalhadores expostos depende de uma fonte de dados capaz de informar quantas pessoas trabalham em determinadas ocupações e atividades econômicas. No Brasil, três grandes fontes são relevantes:

### 4.1 Censo Demográfico

O Censo tem a vantagem de cobrir todo o território nacional, com possibilidade de desagregação municipal. Também capta trabalhadores formais e informais. Sua principal limitação é a periodicidade decenal, que pode tornar as estimativas defasadas, além de restrições práticas no acesso a cruzamentos detalhados de ocupação e atividade econômica.

### 4.2 PNAD e PNAD Contínua

As pesquisas domiciliares captam trabalhadores formais e informais, sendo particularmente relevantes para agentes presentes em atividades com elevada informalidade, como agrotóxicos no trabalho rural. A PNAD Contínua oferece informação conjuntural e boa cobertura territorial em níveis agregados, mas sua capacidade de desagregação por ocupação, atividade econômica e município é limitada por seu desenho amostral.

### 4.3 RAIS

A **Relação Anual de Informações Sociais (RAIS)** é um registro administrativo anual do mercado formal de trabalho. Sua principal vantagem para o Carex-BR é a possibilidade de cruzar ocupação, atividade econômica, território, sexo, remuneração, escolaridade, tipo de vínculo, tamanho do estabelecimento e outras variáveis em longas séries históricas. Além disso, utiliza classificações fiscais regulares, como CBO e CNAE.

Sua limitação central é a cobertura restrita aos vínculos formais e a dependência de informações declaradas pelos estabelecimentos. Portanto, a RAIS é mais adequada para agentes cuja exposição ocorre predominantemente em atividades e ocupações com maior formalização. Para agentes associados a elevada informalidade, a PNAD ou outras fontes podem ser mais apropriadas.

---

## 5. Unidade de classificação: categorias e vínculos

A matriz Carex-BR trabalha com duas dimensões principais da inserção produtiva:

- **CBO**: ocupação desempenhada pelo trabalhador;
- **CNAE**: atividade econômica do estabelecimento ou vínculo.

A exposição ocupacional raramente depende apenas da ocupação ou apenas da atividade econômica. A mesma ocupação pode envolver exposições distintas conforme o setor produtivo, e a mesma atividade econômica pode conter ocupações com níveis de exposição muito diferentes. Por isso, a abordagem preferencial do Carex-BR é a dupla classificação **CBO × CNAE**.

Entretanto, classificar diretamente todos os pares possíveis de CBO e CNAE em níveis detalhados é operacionalmente inviável. O número de combinações cresce rapidamente quando se utilizam níveis mais desagregados das classificações. Para resolver esse problema, o Carex-BR adota uma estratégia em duas etapas:

1. **Classificação independente das categorias CBO e CNAE.**  
   Cada categoria ocupacional e cada categoria de atividade econômica é avaliada por especialistas, em nível hierárquico apropriado.

2. **Consolidação posterior dos vínculos CBO × CNAE.**  
   A classificação final do vínculo é derivada da combinação entre a classificação da ocupação e a classificação da atividade econômica.

Essa estratégia reduz drasticamente o número de classificações necessárias, permite maior detalhamento seletivo e mantém a possibilidade de produzir uma matriz final por par ocupação--atividade econômica.

---

## 6. Categorias de exposição

A classificação geral utilizada no sistema organiza as categorias em quatro estados lógicos:

| Código | Classe | Descrição operacional |
|---:|---|---|
| 0 | Não exposto (NEX) | O agente não é esperado no processo, atividade ou ambiente como insumo, produto, resíduo ou contaminante relevante. |
| 1 | Condicionalmente exposto (CEX) | O agente pode estar presente em subatividades, processos, tarefas ou subcategorias específicas, mas a categoria é inespecífica ou heterogênea. |
| 2 | Exposto (EXP) | O agente tem presença esperada no processo produtivo principal, na atividade característica, como insumo, produto, resíduo ou contaminante. |
| 9 | Não classificado / não aplicável (NCL) | A categoria ainda não foi avaliada ou não se aplica ao agente/matriz em questão. |

A classe **condicionalmente exposto** é metodologicamente importante. Ela não deve ser interpretada como uma exposição intermediária simples. Em muitos casos, ela representa incerteza estrutural da categoria: a descrição é ampla demais, reúne situações produtivas heterogêneas ou depende fortemente do par com a outra dimensão da matriz. Por isso, categorias condicionais devem, sempre que possível, ser refinadas em níveis mais desagregados ou interpretadas à luz do critério de consolidação adotado.

---

## 7. Critério de herança de classificação

As classificações CBO e CNAE são hierárquicas. Uma categoria agregada pode conter subcategorias mais específicas. O Carex-BR utiliza um critério de **herança de classificação** para aumentar a eficiência do processo.

O princípio é o seguinte:

- categorias classificadas como **expostas** ou **não expostas** podem transmitir sua classificação às subcategorias, quando a equipe técnica considera que a classificação é suficientemente estável;
- categorias classificadas como **condicionalmente expostas** exigem, por definição, avaliação em maior nível de detalhe;
- categorias herdadas podem ser reabertas em ciclos de revisão, especialmente quando surgem evidências de erro de classificação, heterogeneidade relevante ou resultados incoerentes nos vínculos finais.

O critério de herança afeta a sensibilidade e a especificidade da matriz. Refinar categorias previamente herdadas como expostas tende a reduzir falsos positivos. Refinar categorias herdadas como não expostas tende a reduzir falsos negativos. Por isso, os ciclos de refino devem ser orientados por evidências, frequência dos vínculos, plausibilidade técnico-produtiva e impacto nas estimativas.

---

## 8. Consolidação dos pares CBO × CNAE

Após a classificação independente de CBO e CNAE, cada vínculo da base populacional precisa receber uma classificação consolidada. Essa etapa combina a classificação da ocupação com a classificação da atividade econômica.

O problema técnico é que diferentes regras de consolidação produzem diferentes matrizes finais. Algumas regras são mais restritivas, priorizando especificidade; outras são mais inclusivas, priorizando sensibilidade. Por isso, o critério de consolidação deve ser escolhido de forma explícita, documentada e coerente com o agente, a definição de exposição e a finalidade da estimativa.

### 8.1 Situações de consolidação

Há quatro situações principais:

#### a) Dupla concordância

Quando CBO e CNAE têm a mesma classificação, a consolidação tende a ser direta. Se ambas são expostas, o vínculo é exposto. Se ambas são não expostas, o vínculo é não exposto. Se ambas são condicionais, a equipe deve decidir se mantém a incerteza, classifica como exposto por precaução, classifica como condicional ou exige revisão par a par.

#### b) Uma dimensão condicional e outra conclusiva

Quando uma dimensão é condicional e a outra é exposta ou não exposta, a dimensão conclusiva pode ser usada como referência complementar. Por exemplo, uma ocupação de manutenção pode ser condicional em abstrato, mas sua exposição pode depender fortemente da atividade econômica onde é exercida.

#### c) Duplo condicional

Quando CBO e CNAE são condicionais, a combinação permanece incerta. Nesses casos, a matriz pode preservar a classificação condicional, aplicar uma regra de sensibilidade, aplicar uma regra de especificidade ou encaminhar os pares mais frequentes para revisão especializada.

#### d) Discordância entre exposto e não exposto

Quando uma dimensão é exposta e a outra não exposta, há potencial incoerência que precisa ser analisada. Pode haver erro de classificação, erro de codificação na RAIS, ocupação exposta em atividade econômica aparentemente não exposta, ou atividade exposta com ocupações administrativas/de apoio efetivamente não expostas.

---

## 9. Critérios operacionais de conciliação

O sistema documenta diferentes regras possíveis de conciliação entre CBO e CNAE. Elas podem ser utilizadas para análise de sensibilidade, comparação de cenários ou escolha final da matriz de cada agente.

| Critério | Nome sintético | Lógica geral | Tendência esperada |
|---:|---|---|---|
| 1 | Interseção estrita | Só classifica como exposto quando CBO e CNAE são ambos expostos. | Mais específico; menor sensibilidade. |
| 2 | Prevalência do exposto | Exige pelo menos um exposto e o outro não pode ser não exposto. | Restritivo, mas menos que o critério 1. |
| 3 | Condição intermediária | Preserva a condição intermediária quando ambos são condicionais. | Mantém incerteza explícita. |
| 4 | Exposição atenuada por oposição | Combinações opostas entre exposto e não exposto são atenuadas para condicional. | Útil para discordâncias plausíveis. |
| 5 | Prevalência de exposto direto | A presença de uma dimensão exposta tende a determinar exposição. | Mais sensível; maior risco de falso positivo. |
| 6 | Prevalência máxima / união | Retorna o maior nível de exposição entre CBO e CNAE. | Mais inclusivo. |
| 7 | Proteção estrita | Valoriza o não exposto, salvo combinações específicas. | Mais conservador. |
| 8 | Proteção atenuada | Discordâncias exposto × não exposto são atenuadas. | Conservador com preservação de incerteza. |
| 9 | Interseção qualificada | Se houver não exposto, não há exposição; se ambos são ao menos condicionais, classifica como exposto. | Específico para exclusão; inclusivo entre condicionais. |
| 10 | Interseção gradual | Se houver não exposto, não há exposição; caso contrário, preserva gradação. | Gradual e relativamente conservador. |

A escolha entre esses critérios não é apenas estatística ou computacional. Ela traduz uma decisão epidemiológica e higienista sobre o que se pretende estimar: trabalhadores provavelmente expostos, potencialmente expostos, expostos em sentido amplo ou grupos prioritários para vigilância.

### 9.1 Detalhamento Matricial dos Critérios de Conciliação

Esta seção analisa detalhadamente os critérios utilizados na view materializada `mvw_matriz_classificacao_conciliada_vinculos` para consolidar a classificação de exposição cruzando dados de **Atividade Econômica (CNAE)** e **Ocupação (CBO)**.

Cada critério (de 1 a 10) define uma lógica específica de combinação (matriz 3x3) onde:
* **0**: Não exposto (NEX)
* **1**: Condicionalmente exposto (CEX)
* **2**: Exposto (EXP)
* **9**: Não aplicável / Não classificado (NCL)

---

#### Critério 1: Interseção Estrita
*Só há exposição se ambas as classificações forem totalmente expostas.*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 0 |
| **Condicionalmente (1)** | 0 | 0 | 0 |
| **Exposto (2)** | 0 | 0 | 2 |

---

#### Critério 2: Prevalência do Exposto
*Só é exposto se ao menos um for exposto e o outro não for "Não exposto".*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 0 |
| **Condicionalmente (1)** | 0 | 0 | 2 |
| **Exposto (2)** | 0 | 2 | 2 |

---

#### Critério 3: Condição de Nível Intermediário
*Semelhante ao Critério 2, mas preserva a condição intermediária se ambos forem condicionalmente expostos.*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 0 |
| **Condicionalmente (1)** | 0 | 1 | 2 |
| **Exposto (2)** | 0 | 2 | 2 |

---

#### Critério 4: Exposição Atenuada por Oposição
*Classificações opostas (0 e 2) resultam em classificação atenuada (1).*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 1 |
| **Condicionalmente (1)** | 0 | 1 | 2 |
| **Exposto (2)** | 1 | 2 | 2 |

---

#### Critério 5: Prevalência de Exposto Direto
*Se qualquer um for exposto (2), o resultado é exposto (2), exceto se o outro for 0 ou 1.*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 2 |
| **Condicionalmente (1)** | 0 | 1 | 2 |
| **Exposto (2)** | 2 | 2 | 2 |

---

#### Critério 6: Prevalência Máxima (União)
*Retorna o maior nível de exposição presente entre as duas classificações.*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 1 | 2 |
| **Condicionalmente (1)** | 1 | 1 | 2 |
| **Exposto (2)** | 2 | 2 | 2 |

---

#### Critério 7: Proteção Estrita (Foco no Não Exposto)
*Se qualquer um for não exposto (0), o resultado é 0, a menos que o outro seja totalmente exposto (2).*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 2 |
| **Condicionalmente (1)** | 0 | 1 | 2 |
| **Exposto (2)** | 0 | 2 | 2 |

---

#### Critério 8: Proteção Atenuada
*Semelhante ao Critério 7, mas se uma das partes for Não Exposto (0) e a outra Exposto (2), o resultado é atenuado para Condicionalmente Exposto (1).*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 1 |
| **Condicionalmente (1)** | 0 | 1 | 2 |
| **Exposto (2)** | 0 | 2 | 2 |

---

#### Critério 9: Interseção Qualificada
*Nenhuma exposição se houver Não Exposto (0). Se ambos forem ao menos condicionalmente expostos, o resultado é totalmente exposto (2).*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 0 |
| **Condicionalmente (1)** | 0 | 2 | 2 |
| **Exposto (2)** | 0 | 2 | 2 |

---

#### Critério 10: Interseção Gradual (Média Lógica)
*Nenhuma exposição se houver Não Exposto (0). Caso contrário, segue um crescimento gradual da exposição.*

| CBO \ CNAE | Não exposto (0) | Condicionalmente (1) | Exposto (2) |
|---|---|---|---|
| **Não exposto (0)** | 0 | 0 | 0 |
| **Condicionalmente (1)** | 0 | 1 | 1 |
| **Exposto (2)** | 0 | 1 | 2 |

---

## 10. Estimativa do número de trabalhadores expostos

A matriz consolidada é aplicada à fonte de dados da força de trabalho. Em termos gerais, a estimativa segue a lógica:

```text
Número de expostos = número de trabalhadores/vínculos na célula CBO × CNAE × proporção de exposição atribuída à célula
```

Quando a matriz utiliza apenas classificação categórica, todos os vínculos classificados como expostos podem ser contabilizados como expostos segundo a definição da matriz. Quando há proporções de exposição, a matriz pode atribuir uma probabilidade ou fração de expostos para uma categoria, ocupação, atividade econômica ou par CBO × CNAE.

Assim, a matriz pode produzir diferentes tipos de saída:

- número absoluto de trabalhadores expostos;
- proporção de trabalhadores expostos no universo analisado;
- perfis por ocupação, atividade econômica, sexo, idade, território, escolaridade, remuneração, tipo de vínculo e tamanho do estabelecimento;
- séries temporais, quando a fonte permite análise anual;
- cenários alternativos conforme critério de consolidação;
- estimativas de trabalhadores potencialmente expostos, quando a classe condicional é preservada ou probabilizada.

No caso da RAIS, a unidade operacional é o vínculo formal. Portanto, as estimativas baseadas nessa fonte devem ser interpretadas como estimativas de vínculos/trabalhadores formais expostos, respeitando os limites da cobertura da base.

---

## 11. Qualidade, incerteza e revisão especializada

As estimativas do Carex-BR devem ser interpretadas como estimativas populacionais de exposição esperada, e não como medições individuais. Elas são adequadas para vigilância, priorização de setores, identificação de grupos potencialmente expostos, planejamento de ações, geração de denominadores e monitoramento de tendências.

As principais fontes de incerteza incluem:

- heterogeneidade tecnológica e organizacional dentro da mesma CNAE;
- heterogeneidade de tarefas dentro da mesma CBO;
- diferenças regionais em processos produtivos e proteção coletiva;
- erros de classificação de CBO e CNAE nas bases administrativas;
- sub-representação de trabalhadores informais quando a fonte é a RAIS;
- ausência de medições ambientais padronizadas em escala nacional;
- necessidade de julgamento especializado em categorias inespecíficas;
- decisões de consolidação que afetam sensibilidade e especificidade.

Por isso, o sistema deve preservar rastreabilidade: agente, versão da matriz, data da classificação, especialista ou grupo responsável, nível hierárquico avaliado, regra de herança, critério de consolidação, justificativa técnica e fonte de evidência utilizada.

---

## 12. Fluxo operacional recomendado no sistema

O fluxo técnico pode ser organizado em oito etapas:

1. **Configuração do agente**  
   Definir agente, escopo, formas de exposição relevantes, critérios mínimos e fontes prioritárias.

2. **Preparação das classificações**  
   Carregar vocabulários CBO e CNAE, níveis hierárquicos, descrições, notas explicativas e tabelas auxiliares.

3. **Classificação inicial das categorias**  
   Especialistas classificam categorias CBO e CNAE como NEX, CEX, EXP ou NCL.

4. **Aplicação da herança**  
   Classificações são propagadas para subcategorias, quando metodologicamente admissível.

5. **Refino dirigido**  
   Categorias condicionais, frequentes ou discordantes são reavaliadas em maior nível de detalhe.

6. **Consolidação dos pares**  
   Aplicar um ou mais critérios de conciliação CBO × CNAE.

7. **Aplicação à base de força de trabalho**  
   Cruzar matriz consolidada com RAIS, PNAD, Censo ou outra fonte definida.

8. **Geração de estimativas e perfis**  
   Produzir estimativas por agente, ocupação, atividade econômica, território, sexo, período e demais variáveis disponíveis.

---

## 13. Uso adequado dos resultados

Os resultados do Carex-BR são apropriados para:

- vigilância populacional de exposições ocupacionais cancerígenas;
- definição de prioridades de inspeção, vigilância e prevenção;
- identificação de setores e ocupações com maior potencial de exposição;
- planejamento de estudos epidemiológicos;
- estimativa de população potencialmente exposta;
- apoio ao cálculo de carga atribuível, quando combinados com estimativas de risco adequadas;
- comunicação técnica com gestores, especialistas e equipes de saúde do trabalhador.

Os resultados não devem ser interpretados como:

- diagnóstico individual de exposição;
- medição ambiental direta;
- prova de nexo causal individual;
- substituto de avaliação higienista em local de trabalho;
- estimativa completa da exposição nacional quando a fonte de força de trabalho exclui trabalhadores informais.

---

## 14. Síntese metodológica

O Carex-BR é um sistema de vigilância e estimativa populacional de exposições ocupacionais a agentes cancerígenos, baseado em matrizes de exposição ocupacional adaptadas à realidade brasileira. Sua metodologia combina a estrutura internacional do CAREX com fontes nacionais de força de trabalho, classificações CBO e CNAE, julgamento de especialistas e critérios explícitos de herança e consolidação.

A principal inovação operacional está em classificar separadamente categorias ocupacionais e econômicas, herdar classificações quando apropriado e consolidar posteriormente os pares CBO × CNAE. Essa solução reduz a carga de classificação, permite maior detalhamento adaptativo, documenta incertezas e torna viável a aplicação da matriz a grandes bases administrativas, como a RAIS.

A qualidade das estimativas depende da coerência entre definição de exposição, nível de desagregação, regra de herança, critério de consolidação, fonte de força de trabalho e revisão especializada. Por isso, cada matriz deve ser tratada como um produto versionado, auditável e passível de aprimoramento progressivo.

---

## 15. Referências técnicas principais

- Kauppinen T, Toikkanen J, Pukkala E. *From crosstabulation to multipurpose exposure information system: a new job-exposure matrix*. American Journal of Industrial Medicine. 1998.
- Kauppinen T, Toikkanen J, Pedersen D, et al. *Occupational exposure to carcinogens in the European Union*. Occupational and Environmental Medicine. 2000.
- Kauppinen T, Uuksulainen S, Saalo A, Mäkinen I, Pukkala E. *Use of the Finnish Information System on Occupational Exposure (FINJEM) in epidemiologic, surveillance, and other applications*. Annals of Occupational Hygiene. 2014.
- Lavoué J, Pintos J, Van Tongeren M, et al. *Comparison of exposure estimates in the Finnish job-exposure matrix FINJEM with a JEM derived from expert assessments performed in Montreal*. Occupational and Environmental Medicine. 2012.
- Pahwa M, Guzman JR, Demers PA, Peters CE, Restrepo MTE, Ge CB, Palmer A. *Developing National CAREX Projects in Latin America and the Caribbean*. Technical Guide. 2016.
- Peters CE, Ge CB, Hall AL, Davies HW, Demers PA. *CAREX Canada: an enhanced model for assessing occupational carcinogen exposure*. Occupational and Environmental Medicine. 2015.
- Ribeiro FSN, Camargo EA, Wünsch Filho V. *Delineamento e validação de matriz de exposição ocupacional à sílica*. Revista de Saúde Pública. 2005.
- Ribeiro FSN, Camargo EA, Algranti E, Wünsch Filho V. *Exposição ocupacional à sílica no Brasil no ano de 2001*. Revista Brasileira de Epidemiologia. 2008.

---

## 16. Nota para manutenção do sistema

Para cada agente e versão de matriz, recomenda-se armazenar no sistema:

- versão da matriz;
- data de criação e revisão;
- agente e definição operacional de exposição;
- fonte de força de trabalho utilizada;
- níveis CBO e CNAE avaliados;
- especialistas participantes;
- fontes técnicas consultadas;
- critério de herança;
- critério de consolidação;
- tratamento das categorias condicionais;
- tratamento de vínculos discordantes;
- uso ou não de probabilidades de exposição;
- escopo da estimativa: vínculos formais, trabalhadores ocupados, população total ocupada ou outro denominador;
- limitações específicas.

Essa documentação é parte essencial da validade operacional do Carex-BR e deve acompanhar qualquer estimativa disponibilizada aos usuários do sistema.
