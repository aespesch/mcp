#!/usr/bin/env python3
"""
Simple File MCP Server - Fixed Version
Corrected to properly handle MCP protocol
"""

import json
import os
import sys
from pathlib import Path
from typing import Any, Dict, Optional

# CRITICAL: Redirect all logs to a file for debugging
LOG_FILE = Path(__file__).parent / "server_debug.log"

def log(message: str):
    """Log to both stderr and file"""
    timestamp = __import__('datetime').datetime.now().strftime("%Y-%m-%d %H:%M:%S.%f")
    log_msg = f"[{timestamp}] [SERVER] {message}\n"
    
    # Write to stderr
    sys.stderr.write(log_msg)
    sys.stderr.flush()
    
    # Write to debug file
    try:
        with open(LOG_FILE, 'a', encoding='utf-8') as f:
            f.write(log_msg)
    except:
        pass

# Log startup
log("=" * 60)
log("SERVER STARTING - FIXED VERSION")
log(f"Python version: {sys.version}")
log(f"Platform: {sys.platform}")
log(f"Script: {__file__}")
log(f"CWD: {os.getcwd()}")

# Working directory
WORKING_DIR = Path(__file__).parent / "mcp_sandbox"
log(f"Working directory: {WORKING_DIR}")

try:
    WORKING_DIR.mkdir(parents=True, exist_ok=True)
    log(f"✓ Sandbox created: {WORKING_DIR.resolve()}")
except Exception as e:
    log(f"✗ Error creating sandbox: {e}")

# Tool definitions
TOOLS = [
    {
        "name": "write_file",
        "description": f"Write content to a file in {WORKING_DIR.resolve()}",
        "inputSchema": {
            "type": "object",
            "properties": {
                "filename": {"type": "string", "description": "Name of the file to write"},
                "content": {"type": "string", "description": "Content to write to the file"}
            },
            "required": ["filename", "content"]
        }
    },
    {
        "name": "read_file",
        "description": f"Read content from a file in {WORKING_DIR.resolve()}",
        "inputSchema": {
            "type": "object",
            "properties": {
                "filename": {"type": "string", "description": "Name of the file to read"}
            },
            "required": ["filename"]
        }
    },
    {
        "name": "list_files",
        "description": f"List all files in {WORKING_DIR.resolve()}",
        "inputSchema": {
            "type": "object",
            "properties": {}
        }
    }
]

def handle_initialize(params: Dict[str, Any]) -> Dict[str, Any]:
    """Handle initialize request"""
    log("INITIALIZE called")
    log(f"  Params: {json.dumps(params)}")
    
    result = {
        "protocolVersion": "2024-11-05",
        "capabilities": {
            "tools": {}
        },
        "serverInfo": {
            "name": "simple-file-server",
            "version": "0.5.0"
        }
    }
    
    log(f"  Result: {json.dumps(result)}")
    return result

def handle_list_tools() -> Dict[str, Any]:
    """Handle tools/list request"""
    log("TOOLS/LIST called")
    result = {"tools": TOOLS}
    log(f"  Returning {len(TOOLS)} tools")
    return result

def handle_list_prompts() -> Dict[str, Any]:
    """Handle prompts/list request - return empty list"""
    log("PROMPTS/LIST called")
    result = {"prompts": []}
    log("  Returning empty prompts list")
    return result

def handle_list_resources() -> Dict[str, Any]:
    """Handle resources/list request - return empty list"""
    log("RESOURCES/LIST called")
    result = {"resources": []}
    log("  Returning empty resources list")
    return result

def handle_call_tool(name: str, arguments: Dict[str, Any]) -> Dict[str, Any]:
    """Handle tools/call request"""
    log(f"TOOLS/CALL: {name}")
    log(f"  Args: {json.dumps(arguments)}")
    
    try:
        if name == "write_file":
            filename = arguments.get("filename")
            content = arguments.get("content", "")
            
            if not filename:
                return {"content": [{"type": "text", "text": "Error: filename is required"}]}
            
            filepath = WORKING_DIR / filename
            
            if not filepath.resolve().is_relative_to(WORKING_DIR.resolve()):
                return {"content": [{"type": "text", "text": "Error: Invalid filename"}]}
            
            filepath.write_text(content, encoding='utf-8')
            msg = f"✓ Wrote to {filepath.resolve()}\n({len(content)} characters)"
            log(f"  Success: {msg}")
            return {"content": [{"type": "text", "text": msg}]}
        
        elif name == "read_file":
            filename = arguments.get("filename")
            
            if not filename:
                return {"content": [{"type": "text", "text": "Error: filename is required"}]}
            
            filepath = WORKING_DIR / filename
            
            if not filepath.resolve().is_relative_to(WORKING_DIR.resolve()):
                return {"content": [{"type": "text", "text": "Error: Invalid filename"}]}
            
            if not filepath.exists():
                return {"content": [{"type": "text", "text": f"Error: File '{filename}' not found"}]}
            
            content = filepath.read_text(encoding='utf-8')
            log(f"  Read {len(content)} characters")
            return {"content": [{"type": "text", "text": content}]}
        
        elif name == "list_files":
            files = [f.name for f in WORKING_DIR.iterdir() if f.is_file()]
            if files:
                file_list = "\n".join(f"  • {f}" for f in sorted(files))
                msg = f"Files in {WORKING_DIR.resolve()}:\n{file_list}"
            else:
                msg = f"No files in {WORKING_DIR.resolve()} yet"
            return {"content": [{"type": "text", "text": msg}]}
        
        else:
            return {"content": [{"type": "text", "text": f"Unknown tool: {name}"}]}
    
    except Exception as e:
        log(f"  ERROR: {e}")
        import traceback
        traceback.print_exc(file=sys.stderr)
        return {"content": [{"type": "text", "text": f"Error: {str(e)}"}]}

def process_request(request: Dict[str, Any]) -> Optional[Dict[str, Any]]:
    """Process a JSON-RPC request"""
    method = request.get("method")
    params = request.get("params", {})
    request_id = request.get("id")
    
    log(f"REQUEST: method={method}, id={request_id}")
    
    try:
        result = None
        
        if method == "initialize":
            result = handle_initialize(params)
        elif method == "initialized" or method == "notifications/initialized":
            # This is a notification, no response needed
            log("  Received initialized notification (no response needed)")
            return None
        elif method == "tools/list":
            result = handle_list_tools()
        elif method == "prompts/list":
            result = handle_list_prompts()
        elif method == "resources/list":
            result = handle_list_resources()
        elif method == "tools/call":
            name = params.get("name", "")
            arguments = params.get("arguments", {})
            result = handle_call_tool(name, arguments)
        else:
            log(f"  Unknown method: {method}")
            return {
                "jsonrpc": "2.0",
                "id": request_id,
                "error": {
                    "code": -32601,
                    "message": f"Method not found: {method}"
                }
            }
        
        response = {
            "jsonrpc": "2.0",
            "id": request_id,
            "result": result
        }
        log(f"  Response prepared for id={request_id}")
        return response
    
    except Exception as e:
        log(f"  ERROR processing request: {e}")
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
    """Send a JSON-RPC message"""
    try:
        json_str = json.dumps(message)
        
        # Try newline-delimited JSON format first (simpler)
        sys.stdout.write(json_str + '\n')
        sys.stdout.flush()
        
        log(f"SENT response id={message.get('id')}")
    except Exception as e:
        log(f"ERROR sending message: {e}")
        import traceback
        traceback.print_exc(file=sys.stderr)

def read_message() -> Optional[Dict[str, Any]]:
    """Read a JSON-RPC message from stdin"""
    try:
        # Read first line
        first_line = sys.stdin.readline().strip()
        if not first_line:
            log("No input (EOF)")
            return None
        
        log(f"READ first line: {first_line[:100]}...")
        
        # Check if it's Content-Length format or direct JSON
        if "Content-Length:" in first_line:
            # Standard MCP format with Content-Length header
            content_length = int(first_line.split(":", 1)[1].strip())
            log(f"Content-Length format: {content_length} bytes")
            
            # Read empty line
            sys.stdin.readline()
            
            # Read content
            content = sys.stdin.read(content_length)
            if not content:
                log("No content")
                return None
            
            message = json.loads(content)
        else:
            # Direct JSON format (Claude Desktop seems to use this)
            log("Direct JSON format")
            message = json.loads(first_line)
        
        log(f"RECEIVED: method={message.get('method')}, id={message.get('id')}")
        return message
    
    except Exception as e:
        log(f"ERROR reading message: {e}")
        import traceback
        traceback.print_exc(file=sys.stderr)
        return None

def main():
    """Main entry point"""
    log("MAIN LOOP starting")
    
    try:
        iteration = 0
        while True:
            iteration += 1
            log(f"--- Iteration {iteration} ---")
            
            message = read_message()
            if message is None:
                log("No message, exiting")
                break
            
            response = process_request(message)
            
            if response:
                send_message(response)
            else:
                log("No response to send (notification)")
        
        log("MAIN LOOP ended normally")
    
    except KeyboardInterrupt:
        log("INTERRUPTED by keyboard")
    except Exception as e:
        log(f"FATAL ERROR: {e}")
        import traceback
        traceback.print_exc(file=sys.stderr)
        sys.exit(1)
    
    log("SERVER SHUTDOWN")
    log("=" * 60)

if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        log(f"EXCEPTION in __main__: {e}")
        import traceback
        traceback.print_exc(file=sys.stderr)
        sys.exit(1)
