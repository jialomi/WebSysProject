import os
import re
import json
import collections

def get_all_php_files(directory):
    php_files = []
    for root, dirs, files in os.walk(directory):
        for file in files:
            if file.endswith('.php') and 'vendor' not in root:
                php_files.append(os.path.join(root, file))
    return php_files

def check_file_for_wcag_w3c(filepath):
    issues = set()
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
            
            # Mask all PHP code (<?php ... ?> and <?= ... ?>) with spaces
            # so the regex won't trip on its contents (like '>' or 'alt=' inside PHP)
            def repl_php(m):
                return ' ' * len(m.group(0))
            
            # Non-greedy match for PHP blocks
            masked_content = re.sub(r'<\?(?:php|=).*?(?:\?>|$)', repl_php, content, flags=re.DOTALL | re.IGNORECASE)
            
            # W3C / WCAG Checklist

            # 1. Image alt
            # Using masked_content so `<img src="<?= ... ?>">` safely looks like `<img src="          ">`
            img_tags = re.finditer(r'<img\s+[^>]*>', masked_content, re.IGNORECASE)
            for img in img_tags:
                if 'alt=' not in img.group(0).lower():
                    # Reference the original content so the error message shows the actual code
                    original_tag = content[img.start():img.end()]
                    issues.add(f"Missing alt attribute on <img> tag => {original_tag[:50]}...")
            
            # 2. HTML lang (only check if <html> is present)
            # Find elements starting with `<html ` or `<html>`
            html_tags = re.finditer(r'<html(?:\s[^>]*|)>', masked_content, re.IGNORECASE)
            for html in html_tags:
                if 'lang=' not in html.group(0).lower():
                    issues.add("Missing lang attribute on <html> tag.")
            
            # 3. Inputs without id/aria-label/title (exclude submit/button/hidden)
            input_tags = re.finditer(r'<input\s+[^>]*>', masked_content, re.IGNORECASE)
            for inp in input_tags:
                tag = inp.group(0).lower()
                if 'type="submit"' in tag or 'type="hidden"' in tag or 'type="button"' in tag:
                    continue
                if 'id=' not in tag and 'aria-label=' not in tag and 'title=' not in tag:
                    original_tag = content[inp.start():inp.end()]
                    issues.add(f"Input missing id/aria-label for accessibility => {original_tag[:50]}...")
            
            # 4. Empty links (no text, no aria-label)
            empty_links = re.finditer(r'<a\s+([^>]*)>\s*</a>', masked_content, re.IGNORECASE)
            for a in empty_links:
                if 'aria-label=' not in a.group(1).lower() and 'title=' not in a.group(1).lower():
                    issues.add("Empty <a> tag without aria-label.")

            # 5. Buttons without text/aria-label
            empty_buttons = re.finditer(r'<button\s+([^>]*)>\s*</button>', masked_content, re.IGNORECASE)
            for b in empty_buttons:
                attrs = b.group(1).lower()
                if 'aria-label=' not in attrs and 'title=' not in attrs:
                    issues.add("Empty <button> tag without aria-label.")

            # 6. Duplicate IDs
            # If multiple identical IDs appear, but they are enclosed in PHP conditionals,
            # they may not be true duplicates on output. We will just report them with a warning.
            ids = re.findall(r'\bid=["\']([^"\']+)["\']', masked_content, re.IGNORECASE)
            duplicates = [item for item, count in collections.Counter(ids).items() if count > 1]
            if duplicates:
                v = ", ".join(duplicates)
                issues.add(f"Possible Duplicate IDs found (check if within PHP conditionals): {v}")
                
    except Exception as e:
        issues.add(f"Could not read file: {e}")
        
    return list(issues)

directory = r"c:\Users\User\OneDrive\Desktop\SIT MODS\Tri 2\INF1005 Web Sys\Group Project\WebSysProject"
php_files = get_all_php_files(directory)

report = {}
for file in php_files:
    issues = check_file_for_wcag_w3c(file)
    if issues:
        # Save relative path
        rel_path = os.path.relpath(file, directory)
        report[rel_path] = issues

with open("w3c_wcag_report.json", "w") as f:
    json.dump(report, f, indent=4)
print("Analysis complete. Found issues in", len(report), "files.")
