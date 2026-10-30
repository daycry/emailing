"""Use the repository's profile components during standard unittest discovery."""
from pathlib import Path
import sys

components = Path(__file__).resolve().parents[3] / 'scripts/mcp_profile'
sys.path.insert(0, str(components))
