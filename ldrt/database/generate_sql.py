import pandas as pd
import numpy as np
import os

xlsx_path = r"c:\xampp\htdocs\ldrt\documents\LDRT.xlsx"
schema_path = r"c:\xampp\htdocs\ldrt\database\schema.sql"
output_path = r"c:\xampp\htdocs\ldrt\database\schema_and_data.sql"

def escape_sql_str(val):
    if pd.isna(val) or val is None:
        return "NULL"
    val_str = str(val).strip().replace("'", "''")
    return f"'{val_str}'"

def val_or_null(val):
    if pd.isna(val) or val is None:
        return "NULL"
    return str(val)

def main():
    print("Reading Excel file...")
    xl = pd.ExcelFile(xlsx_path)
    
    df_cid = xl.parse('CID')
    df_cnae_cbo = xl.parse('CNAECBO')
    df_agentes = xl.parse('Agentes')
    df_agente_cid = xl.parse('AgenteCID')
    df_relatos = xl.parse('Relatos')

    # Read schema.sql content
    with open(schema_path, 'r', encoding='utf-8') as f:
        schema_sql = f.read()

    sql_lines = []
    sql_lines.append("-- Automatically generated SQL file combining schema and data")
    sql_lines.append(schema_sql)
    sql_lines.append("\n-- BEGIN DATA INSERTION --\n")

    # 1. Insert CID
    print("Processing CID...")
    cid_code_to_id = {}
    cid_updates = []
    
    for idx, row in df_cid.iterrows():
        cid_id = idx + 1
        code = str(row['Codigo']).strip()
        cid_code_to_id[code] = cid_id
        
        nivel = row['Nivel']
        descricao = row['Descricao']
        
        sql_lines.append(
            f"INSERT INTO cid (id, codigo, nivel, descricao, parent_id) VALUES "
            f"({cid_id}, {escape_sql_str(code)}, {escape_sql_str(nivel)}, {escape_sql_str(descricao)}, NULL);"
        )
        
        # Prepare parent update if exists
        superior = row['Superior']
        if pd.notna(superior):
            superior = str(superior).strip()
            cid_updates.append((cid_id, superior))

    # 2. Insert CNAECBO
    print("Processing CNAECBO...")
    cnae_cbo_key_to_id = {}
    cnae_cbo_updates = []
    
    for idx, row in df_cnae_cbo.iterrows():
        cnae_cbo_id = idx + 1
        classification = str(row['Classificacao']).strip()
        code = str(row['Codigo']).strip()
        cnae_cbo_key_to_id[(classification, code)] = cnae_cbo_id
        
        desc = row['Descricao']
        nivel = row['Nivel']
        
        sql_lines.append(
            f"INSERT INTO cnae_cbo (id, classificacao, codigo, descricao, nivel, parent_id) VALUES "
            f"({cnae_cbo_id}, {escape_sql_str(classification)}, {escape_sql_str(code)}, {escape_sql_str(desc)}, {escape_sql_str(nivel)}, NULL);"
        )
        
        # Prepare parent update if exists
        ascendente = row['Ascendente']
        if pd.notna(ascendente):
            asc = str(ascendente).strip()
            cnae_cbo_updates.append((cnae_cbo_id, asc, classification))

    # 3. Insert Agentes
    print("Processing Agentes...")
    agente_old_id_to_new_id = {}
    agente_updates = []
    
    for idx, row in df_agentes.iterrows():
        agente_id = idx + 1
        old_id = str(row['IdAgente']).strip()
        agente_old_id_to_new_id[old_id] = agente_id
        
        desc = row['Descricao']
        cas = row['CAS']
        cas_str = str(cas).strip() if pd.notna(cas) else None
        
        sql_lines.append(
            f"INSERT INTO agentes (id, descricao, cas, parent_id, old_id) VALUES "
            f"({agente_id}, {escape_sql_str(desc)}, {escape_sql_str(cas_str)}, NULL, {escape_sql_str(old_id)});"
        )
        
        # Prepare parent update if exists
        id_superior = row['IdSuperior']
        if pd.notna(id_superior):
            sup = str(id_superior).strip()
            agente_updates.append((agente_id, sup))

    # 4. Insert AgenteCID (junction)
    print("Processing AgenteCID...")
    for idx, row in df_agente_cid.iterrows():
        code = str(row['Codigo']).strip()
        agent_old_id = str(row['IdAgente']).strip()
        
        cid_id = cid_code_to_id.get(code)
        agente_id = agente_old_id_to_new_id.get(agent_old_id)
        
        if cid_id and agente_id:
            sql_lines.append(
                f"INSERT INTO agente_cid (agente_id, cid_id) VALUES ({agente_id}, {cid_id}) ON CONFLICT DO NOTHING;"
            )
        else:
            print(f"Warning: AgenteCID reference mismatch! Code: {code} (ID: {cid_id}), AgentOldID: {agent_old_id} (ID: {agente_id})")

    # 5. Insert Relatos
    print("Processing Relatos...")
    for idx, row in df_relatos.iterrows():
        relato_id = idx + 1
        old_id = str(row['IdRelatos']).strip()
        titulo = row['Titulo']
        relato_text = row['Relato']
        
        # Resolve CNAEeCBO (format like 'cnae: 9521500' or 'cbo: 3222')
        cnae_cbo_id = "NULL"
        cnae_cbo_raw = row['CNAEeCBO']
        if pd.notna(cnae_cbo_raw):
            cnae_cbo_raw = str(cnae_cbo_raw).strip()
            if ":" in cnae_cbo_raw:
                parts = cnae_cbo_raw.split(":")
                clazz = parts[0].strip()
                val = parts[1].strip()
                # Try to find match
                resolved_id = cnae_cbo_key_to_id.get((clazz, val))
                if resolved_id:
                    cnae_cbo_id = str(resolved_id)
                else:
                    print(f"Warning: Relato {old_id} references unknown CNAECBO: class={clazz}, val={val}")
        
        # Resolve Agent
        agente_id = "NULL"
        agent_old_id = row['IdAgente']
        if pd.notna(agent_old_id):
            agent_old_id = str(agent_old_id).strip()
            resolved_id = agente_old_id_to_new_id.get(agent_old_id)
            if resolved_id:
                agente_id = str(resolved_id)
            else:
                print(f"Warning: Relato {old_id} references unknown Agent: {agent_old_id}")
                
        # Resolve CID
        cid_id = "NULL"
        cid_code_raw = row['IdCid']
        if pd.notna(cid_code_raw):
            cid_code_raw = str(cid_code_raw).strip()
            resolved_id = cid_code_to_id.get(cid_code_raw)
            if resolved_id:
                cid_id = str(resolved_id)
            else:
                print(f"Warning: Relato {old_id} references unknown CID: {cid_code_raw}")

        sql_lines.append(
            f"INSERT INTO relatos (id, cnae_cbo_id, agente_id, cid_id, titulo, relato, old_id) VALUES "
            f"({relato_id}, {cnae_cbo_id}, {agente_id}, {cid_id}, {escape_sql_str(titulo)}, {escape_sql_str(relato_text)}, {escape_sql_str(old_id)});"
        )

    sql_lines.append("\n-- BEGIN HIERARCHY UPDATES --\n")

    # Update CID parents
    print("Generating CID parent updates...")
    cid_warning_count = 0
    for cid_id, superior in cid_updates:
        parent_id = cid_code_to_id.get(superior)
        if parent_id:
            sql_lines.append(f"UPDATE cid SET parent_id = {parent_id} WHERE id = {cid_id};")
        else:
            cid_warning_count += 1
            if cid_warning_count <= 10:
                print(f"Warning: CID {cid_id} has superior '{superior}' which is not defined in the sheet.")
    if cid_warning_count > 10:
        print(f"Total CID warnings suppressed: {cid_warning_count - 10}")

    # Update CNAECBO parents
    print("Generating CNAECBO parent updates...")
    cnae_cbo_warning_count = 0
    for cnae_cbo_id, ascendente, child_class in cnae_cbo_updates:
        if ":" in ascendente:
            parts = ascendente.split(":")
            clazz = parts[0].strip()
            val = parts[1].strip()
        else:
            clazz = child_class
            val = ascendente
            
        parent_id = cnae_cbo_key_to_id.get((clazz, val))
        if parent_id:
            sql_lines.append(f"UPDATE cnae_cbo SET parent_id = {parent_id} WHERE id = {cnae_cbo_id};")
        else:
            cnae_cbo_warning_count += 1
            if cnae_cbo_warning_count <= 10:
                print(f"Warning: CNAECBO {cnae_cbo_id} of class '{child_class}' has ascendente '{ascendente}' (resolved as class='{clazz}', val='{val}') which was not found.")
    if cnae_cbo_warning_count > 10:
        print(f"Total CNAECBO warnings suppressed: {cnae_cbo_warning_count - 10}")

    # Update Agente parents
    print("Generating Agente parent updates...")
    agente_warning_count = 0
    for agente_id, sup in agente_updates:
        parent_id = agente_old_id_to_new_id.get(sup)
        if parent_id:
            sql_lines.append(f"UPDATE agentes SET parent_id = {parent_id} WHERE id = {agente_id};")
        else:
            agente_warning_count += 1
            if agente_warning_count <= 10:
                print(f"Warning: Agente {agente_id} has superior '{sup}' which was not found.")
    if agente_warning_count > 10:
        print(f"Total Agente warnings suppressed: {agente_warning_count - 10}")

    sql_lines.append("\n-- BEGIN CONSTRAINT ADDITIONS AND VIEWS --\n")
    # Add foreign key constraints back
    sql_lines.append("ALTER TABLE cid ADD CONSTRAINT fk_cid_parent FOREIGN KEY (parent_id) REFERENCES cid(id) ON DELETE SET NULL;")
    sql_lines.append("ALTER TABLE cnae_cbo ADD CONSTRAINT fk_cnae_cbo_parent FOREIGN KEY (parent_id) REFERENCES cnae_cbo(id) ON DELETE SET NULL;")
    sql_lines.append("ALTER TABLE agentes ADD CONSTRAINT fk_agentes_parent FOREIGN KEY (parent_id) REFERENCES agentes(id) ON DELETE SET NULL;")

    # Recreate the view at the very end
    sql_lines.append("""
CREATE OR REPLACE VIEW v_rag_chunks AS
SELECT 
    'agente_cid'::varchar(20) AS chunk_type,
    a.id AS source_id,
    a.descricao AS agent_name,
    c.codigo AS cid_code,
    c.descricao AS cid_name,
    NULL::varchar(10) AS cnae_cbo_type,
    NULL::varchar(50) AS cnae_cbo_code,
    NULL::text AS cnae_cbo_name,
    NULL::text AS relato_title,
    'O agente de risco "' || a.descricao || '" está associado à doença "' || c.descricao || '" (CID-10: ' || c.codigo || ').'::text AS chunk_text
FROM agente_cid ac
JOIN agentes a ON ac.agente_id = a.id
JOIN cid c ON ac.cid_id = c.id

UNION ALL

SELECT 
    'relato'::varchar(20) AS chunk_type,
    r.id AS source_id,
    a.descricao AS agent_name,
    c.codigo AS cid_code,
    c.descricao AS cid_name,
    cc.classificacao AS cnae_cbo_type,
    cc.codigo AS cnae_cbo_code,
    cc.descricao AS cnae_cbo_name,
    r.titulo AS relato_title,
    'Relato de Caso: "' || r.titulo || '". Ocupação/Atividade econômica: ' || COALESCE(cc.descricao, 'Não especificada') || ' (' || COALESCE(cc.classificacao, '') || ': ' || COALESCE(cc.codigo, '') || '). Agente de risco associado: ' || COALESCE(a.descricao, 'Não especificado') || '. Diagnóstico associado: ' || COALESCE(c.descricao, 'Não especificado') || ' (CID-10: ' || COALESCE(c.codigo, '') || '). Descrição do caso: ' || r.relato AS chunk_text
FROM relatos r
LEFT JOIN cnae_cbo cc ON r.cnae_cbo_id = cc.id
LEFT JOIN agentes a ON r.agente_id = a.id
LEFT JOIN cid c ON r.cid_id = c.id;
""")

    print(f"Writing SQL file to {output_path}...")
    with open(output_path, 'w', encoding='utf-8') as f:
        f.write("\n".join(sql_lines))
        
    print("SQL file generated successfully!")

if __name__ == "__main__":
    main()
