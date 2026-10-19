import os
import re
import time
from decimal import Decimal, ROUND_HALF_UP

# =================================================================
# CONFIGURATION
# =================================================================

# Files to monitor for changes
FILES_TO_MONITOR = [
    'style.css',
    'bio_builder_shared.css',
    'chart.js',
    'script.js',
    'bio_builder_layer.js'
]

# The file where versions should be updated
TARGET_FILE = 'index.html'

# Time in seconds between checks
CHECK_INTERVAL = 1 

# =================================================================

def get_current_mtimes():
    """Returns a dictionary mapping filenames to their last modification time."""
    mtimes = {}
    for filename in FILES_TO_MONITOR:
        if os.path.exists(filename):
            mtimes[filename] = os.path.getmtime(filename)
        else:
            mtimes[filename] = None
    return mtimes

def increment_version(match):
    """
    Regex callback to increment the version number by 0.01.
    Maintains the same number of decimal places if possible.
    """
    prefix = match.group(1)   # e.g., "style.css?v="
    current_ver = match.group(2)  # e.g., "11.22"
    
    try:
        # Use Decimal for precise arithmetic
        d_ver = Decimal(current_ver)
        d_inc = Decimal('0.01')
        new_ver = d_ver + d_inc
        
        # Determine how many decimal places to show based on input
        # If input was 11.22, we want 11.23.
        # If input was 11.2, we might want 11.21 or 11.3? 
        # "somando 0.01" suggests we always want at least 2 decimal places.
        
        decimal_places = 2
        if '.' in current_ver:
            decimal_places = max(2, len(current_ver.split('.')[1]))
            
        new_ver_str = format(new_ver, f'.{decimal_places}f')
        
        print(f"  [UPDATE] Version bumped: {current_ver} -> {new_ver_str}")
        return f"{prefix}{new_ver_str}"
    except Exception as e:
        print(f"  [ERROR] Could not increment version '{current_ver}': {e}")
        return match.group(0)

def update_target_file(filename):
    """Searches for the filename in the target file and updates its version."""
    if not os.path.exists(TARGET_FILE):
        print(f"  [ERROR] Target file '{TARGET_FILE}' not found!")
        return False

    print(f"  [DETECTED] Change in {filename}. Updating {TARGET_FILE}...")
    
    try:
        with open(TARGET_FILE, 'r', encoding='utf-8') as f:
            content = f.read()
            
        # Pattern looks for filename?v= followed by digits and dots
        # We escape the filename to handle any dots in it
        pattern = re.compile(rf'({re.escape(filename)}\?v=)([\d\.]+)')
        
        new_content, count = pattern.subn(increment_version, content)
        
        if count > 0:
            with open(TARGET_FILE, 'w', encoding='utf-8') as f:
                f.write(new_content)
            print(f"  [SUCCESS] {count} occurrence(s) updated.")
            return True
        else:
            print(f"  [WARNING] No occurrences of '{filename}?v=' found in {TARGET_FILE}.")
            return False
            
    except Exception as e:
        print(f"  [ERROR] Failed to process {TARGET_FILE}: {e}")
        return False

def main():
    print("=" * 60)
    print(" ENCURTOU.PRO - AUTOMATIC VERSION BUMPER")
    print("=" * 60)
    print(f"Monitoring files: {', '.join(FILES_TO_MONITOR)}")
    print(f"Target file:      {TARGET_FILE}")
    print(f"Check interval:   {CHECK_INTERVAL}s")
    print("-" * 60)
    
    # Initialize mtimes
    last_mtimes = get_current_mtimes()
    
    # Initial check for missing files
    for f, t in last_mtimes.items():
        if t is None:
            print(f"  [INFO] {f} not found locally (will skip until created).")

    print("Running... (Press Ctrl+C to stop)")
    
    try:
        while True:
            time.sleep(CHECK_INTERVAL)
            current_mtimes = get_current_mtimes()
            
            for filename in FILES_TO_MONITOR:
                curr_t = current_mtimes[filename]
                last_t = last_mtimes[filename]
                
                if curr_t is not None:
                    # If file exists and was recently modified
                    if last_t is not None and curr_t > last_t:
                        update_target_file(filename)
                    last_mtimes[filename] = curr_t
                else:
                    # File does not exist or was deleted
                    if last_t is not None:
                        print(f"  [INFO] {filename} was deleted or moved.")
                        last_mtimes[filename] = None
                        
    except KeyboardInterrupt:
        print("\n" + "=" * 60)
        print(" Script stopped by user. Goodbye!")
        print("=" * 60)

if __name__ == "__main__":
    main()
