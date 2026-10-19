#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Git Auto Push Helper
--------------------
Script genérico para automatizar o processo de commit e push para o GitHub.
Basta copiar este arquivo para a pasta raiz de qualquer projeto e executá-lo:
    python git_auto_push.py
"""

import os
import subprocess
import sys

def run_captured_command(args):
    """Executa um comando e captura a saída (útil para checagens internas)."""
    try:
        result = subprocess.run(
            args,
            text=True,
            capture_output=True,
            check=False
        )
        return result.stdout.strip(), result.stderr.strip(), result.returncode
    except Exception as e:
        return "", str(e), -1

def run_interactive_command(args):
    """Executa um comando exibindo a saída em tempo real e permitindo interação (como login do Git)."""
    try:
        result = subprocess.run(
            args,
            text=True,
            check=False
        )
        return result.returncode
    except Exception as e:
        print(f"Erro ao executar comando: {' '.join(args)}\nErro: {e}")
        return -1

def main():
    print("=========================================")
    print("        Git Auto Push Helper             ")
    print("=========================================")

    # 1. Verifica se o Git está instalado no sistema
    _, _, code = run_captured_command(["git", "--version"])
    if code != 0:
        print("[ERRO] Git não encontrado no PATH do sistema. Por favor, instale o Git.")
        sys.exit(1)

    # 2. Inicializa o repositório se não existir a pasta .git
    if not os.path.exists(".git"):
        print("\n[INFO] Repositório Git não detectado. Inicializando repositório local...")
        code = run_interactive_command(["git", "init"])
        if code != 0:
            print("[ERRO] Falha ao inicializar o repositório local.")
            sys.exit(1)

    # 3. Verifica configuração do controle remoto 'origin'
    stdout, _, _ = run_captured_command(["git", "remote"])
    remotes = stdout.splitlines()

    if "origin" not in remotes:
        print("\n[CONFIG] Repositório remoto 'origin' não configurado.")
        url = input("Por favor, informe a URL do repositório remoto (ex: https://github.com/usuario/repo.git): ").strip()
        while not url:
            url = input("A URL não pode ser vazia. Digite a URL: ").strip()
        
        code = run_interactive_command(["git", "remote", "add", "origin", url])
        if code != 0:
            print("[ERRO] Falha ao adicionar repositório remoto.")
            sys.exit(1)
        print(f"[SUCESSO] Remoto 'origin' configurado para: {url}")
    else:
        # Se já tem origin configurado, apenas mostra a URL atual para confirmação
        url, _, _ = run_captured_command(["git", "remote", "get-url", "origin"])
        print(f"\n[INFO] Repositório remoto atual: {url}")

    # 4. Oferece a criação de um arquivo .gitignore padrão se não houver um
    if not os.path.exists(".gitignore"):
        print("\n[Aviso] Nenhum arquivo '.gitignore' foi encontrado na pasta raiz.")
        criar_ignore = input("Deseja gerar um arquivo '.gitignore' padrão para projetos Python/Web? (s/n): ").strip().lower()
        if criar_ignore in ['s', 'sim', 'y', 'yes']:
            gitignore_content = (
                "# Python cache\n"
                "__pycache__/\n"
                "*.pyc\n"
                "*.pyo\n"
                "*.pyd\n"
                ".venv/\n"
                "venv/\n"
                "ENV/\n"
                "env/\n"
                "\n"
                "# Logs\n"
                "*.log\n"
                "\n"
                "# Build artifacts\n"
                "build/\n"
                "dist/\n"
                "*.spec\n"
                "*.war\n"
                "\n"
                "# Configurações locais / senhas\n"
                "config/settings.json\n"
                "config/main_monitor_settings.json\n"
                "HTmonitor/config/main_monitor_settings.json\n"
                ".vscode/\n"
                ".idea/\n"
            )
            try:
                with open(".gitignore", "w", encoding="utf-8") as f:
                    f.write(gitignore_content)
                print("[SUCESSO] Arquivo '.gitignore' gerado com sucesso!")
            except Exception as e:
                print(f"[Aviso] Não foi possível criar o arquivo '.gitignore': {e}")

    # 5. Descobre o nome da branch atual
    branch, _, _ = run_captured_command(["git", "branch", "--show-current"])
    if not branch:
        # Se não há commits na branch, 'git branch --show-current' retorna vazio
        head_ref, _, _ = run_captured_command(["git", "symbolic-ref", "--short", "HEAD"])
        branch = head_ref if head_ref else "main"

    # 6. Adiciona todas as modificações à staging area (git add .)
    print("\n[INFO] Escaneando e adicionando modificações...")
    run_interactive_command(["git", "add", "."])

    # 7. Verifica se há alterações para commitar
    status, _, _ = run_captured_command(["git", "status", "--porcelain"])
    if not status:
        print("[INFO] Nenhuma alteração detectada para commit. O repositório está atualizado.")
        sys.exit(0)

    # 8. Mostra as alterações pendentes de forma resumida
    print("\nAlterações a serem commitadas:")
    run_interactive_command(["git", "status", "-s"])

    # 9. Pede a mensagem do commit ao usuário
    commit_msg = input("\nDigite a mensagem do commit: ").strip()
    while not commit_msg:
        commit_msg = input("A mensagem do commit é obrigatória. Digite a mensagem: ").strip()

    # 10. Executa o Commit
    print("\n[INFO] Executando commit...")
    code = run_interactive_command(["git", "commit", "-m", commit_msg])
    if code != 0:
        print("[ERRO] O commit falhou.")
        sys.exit(1)

    # 11. Executa o Push para a branch correspondente
    print(f"\n[INFO] Enviando alterações para origin/{branch}...")
    # Tenta definir a branch padrão na primeira subida utilizando -u
    code = run_interactive_command(["git", "push", "-u", "origin", branch])
    if code != 0:
        print("[Aviso] Push com '-u' falhou. Tentando push direto...")
        code = run_interactive_command(["git", "push", "origin", "HEAD"])
        if code != 0:
            print("[ERRO] Falha ao enviar alterações para o servidor remoto.")
            sys.exit(1)

    print("\n=========================================")
    print(" [SUCESSO] Processo concluído com êxito!  ")
    print("=========================================")

if __name__ == "__main__":
    main()
