import re

filepath = r'd:\bian\apex-frontend\backend\app\Http\Controllers\InventoryController.php'

with open(filepath, 'r', encoding='utf-8') as f:
    content = f.read()

# Replace all "$user = Auth::user();" with type-hinted version
# But skip if already has the annotation on the line before
lines = content.split('\n')
result = []

for i, line in enumerate(lines):
    # Check if this line has Auth::user() assignment
    stripped = line.strip()
    if stripped == '$user = Auth::user();' or stripped == '$targetUser = Auth::user();':
        # Check if previous line already has the @var annotation
        if i > 0 and '/** @var' in result[-1] and 'User' in result[-1]:
            result.append(line)
            continue
        # Add type hint before this line
        indent = line[:len(line) - len(line.lstrip())]
        if '$targetUser' in stripped:
            result.append(f'{indent}/** @var \\App\\Models\\User $targetUser */')
        else:
            result.append(f'{indent}/** @var \\App\\Models\\User $user */')
        result.append(line)
    else:
        result.append(line)

content = '\n'.join(result)

with open(filepath, 'w', encoding='utf-8') as f:
    f.write(content)

print('Done - added type hints')
