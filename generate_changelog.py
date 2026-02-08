
import os
import re

def generate_changelog():
    commits_file = 'commits_list_utf8.txt'
    changes_file = 'changes_list_utf8.txt'
    output_file = 'CHANGELOG_SINCE_2025_08_27.md'
    ignore_paths = [
        'app/common/library/dompdf',
        'app/common/library/htmlpurifier',
        'app/common/library/jpgraph',
        'app/common/library/phpmailer'
    ]

    # Read Commits
    commits = []
    if os.path.exists(commits_file):
        with open(commits_file, 'r', encoding='utf-8') as f:
            raw_commits = f.readlines()
            
        for line in raw_commits:
            line = line.strip()
            if not line: continue
            
            # Extract date
            date_match = re.search(r'\(\d{4}-\d{2}-\d{2}\)$', line)
            date_str = date_match.group(0) if date_match else ""
            
            # Remove date from line for processing
            line_content = line
            if date_str:
                line_content = line.replace(date_str, "").strip()
            
            parts = line_content.split('\t')
            header = parts[0] # "HASH - "
            changes = parts[1:]
            
            filtered_changes = []
            for change in changes:
                should_ignore = False
                for path in ignore_paths:
                    if path in change:
                        should_ignore = True
                        break
                if should_ignore:
                    continue
                filtered_changes.append(change)
            
            # If original had changes but all were filtered, skip the commit
            if len(changes) > 0 and not filtered_changes:
                continue
                
            # Reconstruct line
            # Ensure proper spacing/tabbing
            new_line = header
            if filtered_changes:
                new_line += "\t" + "\t".join(filtered_changes)
            
            if date_str:
                new_line += " " + date_str
                
            commits.append(new_line)

    # Read Changes
    added_files = []
    modified_files = []
    deleted_files = []

    if os.path.exists(changes_file):
        with open(changes_file, 'r', encoding='utf-8') as f:
            for line in f:
                parts = line.strip().split('\t')
                if len(parts) >= 2:
                    status = parts[0]
                    file_path = parts[1]
                    
                    should_ignore = False
                    for path in ignore_paths:
                        if file_path.startswith(path):
                            should_ignore = True
                            break
                    if should_ignore:
                        continue
                    
                    if status.startswith('A'):
                        added_files.append(file_path)
                    elif status.startswith('M'):
                        modified_files.append(file_path)
                    elif status.startswith('D'):
                        deleted_files.append(file_path)

    # Generate Markdown Content
    with open(output_file, 'w', encoding='utf-8') as f:
        f.write('# Changelog (Since August 27, 2025)\n\n')
        f.write('**Date Range:** August 27, 2025 - Present\n\n')
        
        f.write('## Summary\n')
        f.write(f'- **Total Commits:** {len(commits)}\n')
        f.write(f'- **Files Added:** {len(added_files)}\n')
        f.write(f'- **Files Modified:** {len(modified_files)}\n')
        f.write(f'- **Files Deleted:** {len(deleted_files)}\n\n')

        f.write('## Commit History\n')
        if commits:
            for commit in commits:
                f.write(f'- {commit}\n')
        else:
            f.write('No commits found in the specified range.\n')
        f.write('\n')

        f.write('## File Changes\n\n')

        f.write('### Added Files\n')
        if added_files:
            for file in sorted(added_files):
                f.write(f'- `{file}`\n')
        else:
            f.write('No files added.\n')
        f.write('\n')

        f.write('### Modified Files\n')
        if modified_files:
            for file in sorted(modified_files):
                f.write(f'- `{file}`\n')
        else:
            f.write('No files modified.\n')
        f.write('\n')

        f.write('### Deleted Files\n')
        if deleted_files:
            for file in sorted(deleted_files):
                f.write(f'- `{file}`\n')
        else:
            f.write('No files deleted.\n')
        f.write('\n')

    print(f"Successfully generated {output_file}")

if __name__ == "__main__":
    generate_changelog()
