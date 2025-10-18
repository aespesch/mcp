#!/usr/bin/env python3
"""
Thales MCP Server - Ultra Robust Windows Version
"""

import json
import os
import sys
from pathlib import Path
from typing import Any, Dict, Optional
from datetime import datetime

# CRITICAL: Log to file for debugging
LOG_FILE = Path(__file__).parent / "thales_debug.log"

def log(message: str):
    """Log to both stderr and file"""
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S.%f")
    log_msg = f"[{timestamp}] [THALES] {message}\n"
    
    sys.stderr.write(log_msg)
    sys.stderr.flush()
    
    try:
        with open(LOG_FILE, 'a', encoding='utf-8') as f:
            f.write(log_msg)
    except:
        pass

# Log startup
log("=" * 60)
log("THALES SERVER STARTING")
log(f"Python version: {sys.version}")
log(f"Platform: {sys.platform}")
log(f"Script: {__file__}")

# Working directory
WORKING_DIR = Path(__file__).parent / "mcp_sandbox"
THALES_INPUT = WORKING_DIR / "THALES_INPUT.TXT"
THALES_OUTPUT = WORKING_DIR / "THALES_OUTPUT.TXT"

log(f"Working directory: {WORKING_DIR}")

try:
    WORKING_DIR.mkdir(parents=True, exist_ok=True)
    log(f"✓ Sandbox created")
    
    # Initialize Thales files
    if not THALES_INPUT.exists():
        THALES_INPUT.write_text(
            "# THALES_INPUT.TXT - Claude writes here\n",
            encoding='utf-8'
        )
    if not THALES_OUTPUT.exists():
        THALES_OUTPUT.write_text(
            "# THALES_OUTPUT.TXT - Thales responds here\n",
            encoding='utf-8'
        )
    log(f"✓ Thales files ready")
except Exception as e:
    log(f"✗ Error in setup: {e}")

# Tool definitions
TOOLS = [
    {
        "name": "write_file",
        "description": f"Write to file in {WORKING_DIR.resolve()}",
        "inputSchema": {
            "type": "object",
            "properties": {
                "filename": {"type": "string"},
                "content": {"type": "string"}
            },
            "required": ["filename", "content"]
        }
    },
    {
        "name": "read_file",
        "description": f"Read file from {WORKING_DIR.resolve()}",
        "inputSchema": {
            "type": "object",
            "properties": {
                "filename": {"type": "string"}
            },
            "required": ["filename"]
        }
    },
    {
        "name": "list_files",
        "description": f"List files in {WORKING_DIR.resolve()}",
        "inputSchema": {"type": "object", "properties": {}}
    },
    {
        "name": "thales_write",
        "description": "Send message to Thales",
        "inputSchema": {
            "type": "object",
            "properties": {
                "message": {"type": "string"}
            },
            "required": ["message"]
        }
    },
    {
        "name": "thales_read",
        "description": "Read Thales response",
        "inputSchema": {"type": "object", "properties": {}}
    },
    {
        "name": "thales_status",
        "description": "Check Thales communication status",
        "inputSchema": {"type": "object", "properties": {}}
    }
]

def handle_initialize(params: Dict[str, Any]) -> Dict[str, Any]:
    """Handle initialize"""
    log("INITIALIZE called")
    return {
        "protocolVersion": "2024-11-05",
        "capabilities": {"tools": {}},
        "serverInfo": {
            "name": "thales-mcp-server",
            "version": "1.2.0"
        }
    }

def handle_list_tools() -> Dict[str, Any]:
    """Handle tools/list"""
    log("TOOLS/LIST called")
    return {"tools": TOOLS}

def handle_call_tool(name: str, arguments: Dict[str, Any]) -> Dict[str, Any]:
    """Handle tools/call"""
    log(f"TOOLS/CALL: {name}")
    
    try:
        if name == "thales_write":
            message = arguments.get("message", "")
            if not message:
                return {"content": [{"type": "text", "text": "Error: message required"}]}
            
            existing = THALES_INPUT.read_text(encoding='utf-8')
            timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            new_line = f"[{timestamp}] {message}\n"
            THALES_INPUT.write_text(existing + new_line, encoding='utf-8')
            
            result = f"✓ Message sent to Thales at {timestamp}:\n{message}\n\nFile: {THALES_INPUT.resolve()}"
            log(f"  Wrote to Thales")
            return {"content": [{"type": "text", "text": result}]}
        
        elif name == "thales_read":
            content = THALES_OUTPUT.read_text(encoding='utf-8')
            result = f"Thales Output (from {THALES_OUTPUT.resolve()}):\n\n{content}"
            log("  Read Thales output")
            return {"content": [{"type": "text", "text": result}]}
        
        elif name == "thales_status":
            input_exists = THALES_INPUT.exists()
            output_exists = THALES_OUTPUT.exists()
            
            status = f"""Thales Status:

INPUT:  {THALES_INPUT.resolve()}
        Exists: {input_exists}
        Size: {THALES_INPUT.stat().st_size if input_exists else 0} bytes

OUTPUT: {THALES_OUTPUT.resolve()}
        Exists: {output_exists}
        Size: {THALES_OUTPUT.stat().st_size if output_exists else 0} bytes"""
            
            return {"content": [{"type": "text", "text": status}]}
        
        elif name == "write_file":
            filename = arguments.get("filename")
            content = arguments.get("content", "")
            
            if not filename:
                return {"content": [{"type": "text", "text": "Error: filename required"}]}
            
            filepath = WORKING_DIR / filename
            
            if not filepath.resolve().is_relative_to(WORKING_DIR.resolve()):
                return {"content": [{"type": "text", "text": "Error: Invalid filename"}]}
            
            filepath.write_text(content, encoding='utf-8')
            msg = f"✓ Wrote to {filepath.resolve()}"
            log(f"  {msg}")
            return {"content": [{"type": "text", "text": msg}]}
        
        elif name == "read_file":
            filename = arguments.get("filename")
            
            if not filename:
                return {"content": [{"type": "text", "text": "Error: filename required"}]}
            
            filepath = WORKING_DIR / filename
            
            if not filepath.resolve().is_relative_to(WORKING_DIR.resolve()):
                return {"content": [{"type": "text", "text": "Error: Invalid filename"}]}
            
            if not filepath.exists():
                return {"content": [{"type": "text", "text": f"Error: '{filename}' not found"}]}
            
            content = filepath.read_text(encoding='utf-8')
            log(f"  Read {len(content)} chars")
            return {"content": [{"type": "text", "text": content}]}
        
        elif name == "list_files":
            files = [f.name for f in WORKING_DIR.iterdir() if f.is_file()]
            
            if files:
                thales = [f for f in files if f.upper() in ["THALES_INPUT.TXT", "THALES_OUTPUT.TXT"]]
                regular = [f for f in files if f not in thales]
                
                msg = f"Files in {WORKING_DIR.resolve()}:\n"
                
                if thales:
                    msg += "\n🔮 Thales Files:\n"
                    msg += "\n".join(f"   • {f}" for f in sorted(thales))
                
                if regular:
                    msg += "\n\n📄 Regular Files:\n"
                    msg += "\n".join(f"   • {f}" for f in sorted(regular))
            else:
                msg = f"No files in {WORKING_DIR.resolve()}"
            
            return {"content": [{"type": "text", "text": msg}]}
        
        else:
            return {"content": [{"type": "text", "text": f"Unknown tool: {name}"}]}
    
    except Exception as e:
        log(f"  ERROR: {e}")
        import traceback
        traceback.print_exc(file=sys.stderr)
        return {"content": [{"type": "text", "text": f"Error: {str(e)}"}]}

def process_request(request: Dict[str, Any]) -> Optional[Dict[str, Any]]:
    """Process request"""
    method = request.get("method")
    params = request.get("params", {})
    request_id = request.get("id")
    
    log(f"REQUEST: method={method}, id={request_id}")
    
    try:
        result = None
        
        if method == "initialize":
            result = handle_initialize(params)
        elif method == "initialized":
            log("  Initialized notification")
            return None
        elif method == "tools/list":
            result = handle_list_tools()
        elif method == "tools/call":
            name = params.get("name", "")
            arguments = params.get("arguments", {})
            result = handle_call_tool(name, arguments)
        else:
            return {
                "jsonrpc": "2.0",
                "id": request_id,
                "error": {
                    "code": -32601,
                    "message": f"Method not found: {method}"
                }
            }
        
        return {
            "jsonrpc": "2.0",
            "id": request_id,
            "result": result
        }
    
    except Exception as e:
        log(f"  ERROR: {e}")
        import traceback
        traceback.print_exc(file=sys.stderr)
        return {
            "jsonrpc": "2.0",
            "id": request_id,
            "error": {
                "code": -32603,
                "message": "Internal error",
                "data": str(e)
            }
        }

def send_message(message: Dict[str, Any]):
    """Send message"""
    try:
        json_str = json.dumps(message)
        sys.stdout.write(json_str + '\n')
        sys.stdout.flush()
        
        log(f"SENT response id={message.get('id')}")
    except Exception as e:
        log(f"ERROR sending: {e}")

def read_message() -> Optional[Dict[str, Any]]:
    """Read message"""
    try:
        # Read first line
        first_line = sys.stdin.readline().strip()
        if not first_line:
            log("EOF")
            return None
        
        log(f"READ: {first_line[:80]}...")
        
        # Check format
        if "Content-Length:" in first_line:
            # Standard MCP format
            content_length = int(first_line.split(":", 1)[1].strip())
            sys.stdin.readline()  # empty line
            content = sys.stdin.read(content_length)
            if not content:
                return None
            message = json.loads(content)
        else:
            # Direct JSON format
            message = json.loads(first_line)
        
        log(f"RECEIVED: method={message.get('method')}, id={message.get('id')}")
        return message
    
    except Exception as e:
        log(f"ERROR reading: {e}")
        return None

def main():
    """Main loop"""
    log("MAIN LOOP starting")
    
    try:
        iteration = 0
        while True:
            iteration += 1
            log(f"--- Iteration {iteration} ---")
            
            message = read_message()
            if message is None:
                break
            
            response = process_request(message)
            if response:
                send_message(response)
        
        log("MAIN LOOP ended")
    except KeyboardInterrupt:
        log("INTERRUPTED")
    except Exception as e:
        log(f"FATAL: {e}")
        import traceback
        traceback.print_exc(file=sys.stderr)
        sys.exit(1)
    
    log("=" * 60)

if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        log(f"EXCEPTION: {e}")
        sys.exit(1)
