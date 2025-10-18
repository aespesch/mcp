#!/usr/bin/env python3
"""
Thales-Enabled MCP Server - Combines file operations with Thales communication
Allows Claude to interact with Thales through special file operations
"""

import json
import os
import sys
import asyncio
from pathlib import Path
from typing import Any, Dict, List, Optional
import traceback
from datetime import datetime

# Define the working directory
WORKING_DIR = Path("./mcp_sandbox")
WORKING_DIR.mkdir(exist_ok=True)

# Thales special files
THALES_INPUT_FILE = WORKING_DIR / "THALES_INPUT.TXT"
THALES_OUTPUT_FILE = WORKING_DIR / "THALES_OUTPUT.TXT"

class ThalesMCPServer:
    def __init__(self):
        self.initialized = False
        print(f"[SERVER] Starting Thales MCP Server...", file=sys.stderr)
        print(f"[SERVER] Sandbox directory: {WORKING_DIR.resolve()}", file=sys.stderr)
        
        # Initialize Thales files
        self._init_thales_files()
    
    def _init_thales_files(self):
        """Initialize Thales communication files"""
        if not THALES_INPUT_FILE.exists():
            THALES_INPUT_FILE.write_text(
                "# THALES_INPUT.TXT - Claude writes questions here\n"
                "# Each line should be timestamped\n"
            )
        
        if not THALES_OUTPUT_FILE.exists():
            THALES_OUTPUT_FILE.write_text(
                "# THALES_OUTPUT.TXT - Thales writes responses here\n"
                "# Waiting for Thales...\n"
            )
        
        print(f"[SERVER] Thales files initialized", file=sys.stderr)
    
    async def handle_initialize(self, params: Dict[str, Any]) -> Dict[str, Any]:
        """Handle initialization request"""
        print(f"[SERVER] Initialize called", file=sys.stderr)
        self.initialized = True
        return {
            "protocolVersion": "2024-11-05",
            "capabilities": {
                "tools": {
                    "listChanged": False
                }
            },
            "serverInfo": {
                "name": "thales-mcp-server",
                "version": "1.0.0"
            }
        }
    
    async def handle_list_tools(self) -> Dict[str, Any]:
        """Handle tools/list request"""
        print(f"[SERVER] List tools called", file=sys.stderr)
        tools = [
            # Standard file operations
            {
                "name": "write_file",
                "description": "Write content to a file in the sandbox directory",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "filename": {
                            "type": "string",
                            "description": "Name of the file to write"
                        },
                        "content": {
                            "type": "string", 
                            "description": "Content to write to the file"
                        }
                    },
                    "required": ["filename", "content"]
                }
            },
            {
                "name": "read_file",
                "description": "Read content from a file in the sandbox directory",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "filename": {
                            "type": "string",
                            "description": "Name of the file to read"
                        }
                    },
                    "required": ["filename"]
                }
            },
            {
                "name": "list_files",
                "description": "List all files in the sandbox directory",
                "inputSchema": {
                    "type": "object",
                    "properties": {}
                }
            },
            # Thales-specific tools
            {
                "name": "thales_write",
                "description": "Send a message to Thales (writes to THALES_INPUT.TXT with timestamp)",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "message": {
                            "type": "string",
                            "description": "Message to send to Thales"
                        }
                    },
                    "required": ["message"]
                }
            },
            {
                "name": "thales_read",
                "description": "Read Thales' response (reads from THALES_OUTPUT.TXT)",
                "inputSchema": {
                    "type": "object",
                    "properties": {}
                }
            },
            {
                "name": "thales_status",
                "description": "Check the status of Thales communication files",
                "inputSchema": {
                    "type": "object",
                    "properties": {}
                }
            }
        ]
        return {"tools": tools}
    
    async def handle_call_tool(self, name: str, arguments: Dict[str, Any]) -> Dict[str, Any]:
        """Handle tools/call request"""
        print(f"[SERVER] Tool called: {name}", file=sys.stderr)
        
        # Thales-specific tools
        if name == "thales_write":
            message = arguments.get("message", "")
            if not message:
                return {
                    "content": [{
                        "type": "text",
                        "text": "Error: message is required"
                    }]
                }
            
            try:
                # Read existing content
                existing = THALES_INPUT_FILE.read_text()
                
                # Add timestamp and message
                timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                new_line = f"[{timestamp}] {message}\n"
                
                # Append to file
                THALES_INPUT_FILE.write_text(existing + new_line)
                
                return {
                    "content": [{
                        "type": "text",
                        "text": f"✓ Message sent to Thales at {timestamp}:\n{message}"
                    }]
                }
            except Exception as e:
                return {
                    "content": [{
                        "type": "text",
                        "text": f"Error writing to Thales: {str(e)}"
                    }]
                }
        
        elif name == "thales_read":
            try:
                content = THALES_OUTPUT_FILE.read_text()
                return {
                    "content": [{
                        "type": "text",
                        "text": f"Thales Output:\n{content}"
                    }]
                }
            except Exception as e:
                return {
                    "content": [{
                        "type": "text",
                        "text": f"Error reading Thales response: {str(e)}"
                    }]
                }
        
        elif name == "thales_status":
            try:
                input_exists = THALES_INPUT_FILE.exists()
                output_exists = THALES_OUTPUT_FILE.exists()
                
                input_size = THALES_INPUT_FILE.stat().st_size if input_exists else 0
                output_size = THALES_OUTPUT_FILE.stat().st_size if output_exists else 0
                
                input_modified = datetime.fromtimestamp(THALES_INPUT_FILE.stat().st_mtime).strftime("%Y-%m-%d %H:%M:%S") if input_exists else "N/A"
                output_modified = datetime.fromtimestamp(THALES_OUTPUT_FILE.stat().st_mtime).strftime("%Y-%m-%d %H:%M:%S") if output_exists else "N/A"
                
                status = f"""Thales Communication Status:
                
THALES_INPUT.TXT:
  - Exists: {input_exists}
  - Size: {input_size} bytes
  - Last modified: {input_modified}
  
THALES_OUTPUT.TXT:
  - Exists: {output_exists}
  - Size: {output_size} bytes
  - Last modified: {output_modified}"""
                
                return {
                    "content": [{
                        "type": "text",
                        "text": status
                    }]
                }
            except Exception as e:
                return {
                    "content": [{
                        "type": "text",
                        "text": f"Error checking status: {str(e)}"
                    }]
                }
        
        # Standard file operations
        elif name == "write_file":
            filename = arguments.get("filename")
            content = arguments.get("content", "")
            
            if not filename:
                return {
                    "content": [{
                        "type": "text",
                        "text": "Error: filename is required"
                    }]
                }
            
            # Don't allow overwriting Thales files through write_file
            if filename.upper() in ["THALES_INPUT.TXT", "THALES_OUTPUT.TXT"]:
                return {
                    "content": [{
                        "type": "text",
                        "text": "Error: Use thales_write to communicate with Thales"
                    }]
                }
            
            filepath = WORKING_DIR / filename
            
            # Security check
            try:
                if not filepath.resolve().is_relative_to(WORKING_DIR.resolve()):
                    return {
                        "content": [{
                            "type": "text",
                            "text": "Error: Invalid filename. File must be in sandbox directory."
                        }]
                    }
            except ValueError:
                return {
                    "content": [{
                        "type": "text",
                        "text": "Error: Invalid filename path"
                    }]
                }
            
            try:
                filepath.write_text(content, encoding='utf-8')
                msg = f"Successfully wrote {len(content)} characters to {filename}"
                return {
                    "content": [{
                        "type": "text",
                        "text": msg
                    }]
                }
            except Exception as e:
                return {
                    "content": [{
                        "type": "text",
                        "text": f"Error writing file: {str(e)}"
                    }]
                }
        
        elif name == "read_file":
            filename = arguments.get("filename")
            
            if not filename:
                return {
                    "content": [{
                        "type": "text",
                        "text": "Error: filename is required"
                    }]
                }
            
            filepath = WORKING_DIR / filename
            
            # Security check
            try:
                if not filepath.resolve().is_relative_to(WORKING_DIR.resolve()):
                    return {
                        "content": [{
                            "type": "text",
                            "text": "Error: Invalid filename. File must be in sandbox directory."
                        }]
                    }
            except ValueError:
                return {
                    "content": [{
                        "type": "text",
                        "text": "Error: Invalid filename path"
                    }]
                }
            
            try:
                content = filepath.read_text(encoding='utf-8')
                return {
                    "content": [{
                        "type": "text",
                        "text": content
                    }]
                }
            except FileNotFoundError:
                return {
                    "content": [{
                        "type": "text",
                        "text": f"Error: File '{filename}' not found"
                    }]
                }
            except Exception as e:
                return {
                    "content": [{
                        "type": "text",
                        "text": f"Error reading file: {str(e)}"
                    }]
                }
        
        elif name == "list_files":
            try:
                files = [f.name for f in WORKING_DIR.iterdir() if f.is_file()]
                if files:
                    # Organize files
                    thales_files = []
                    regular_files = []
                    
                    for f in sorted(files):
                        if f.upper() in ["THALES_INPUT.TXT", "THALES_OUTPUT.TXT"]:
                            thales_files.append(f)
                        else:
                            regular_files.append(f)
                    
                    msg = "Files in sandbox:\n"
                    
                    if thales_files:
                        msg += "\nThales Communication Files:\n"
                        msg += "\n".join(f"  • {f}" for f in thales_files)
                    
                    if regular_files:
                        msg += "\n\nRegular Files:\n"
                        msg += "\n".join(f"  • {f}" for f in regular_files)
                    
                    if not thales_files and not regular_files:
                        msg = "No files in sandbox directory yet"
                else:
                    msg = "No files in sandbox directory yet"
                
                return {
                    "content": [{
                        "type": "text",
                        "text": msg
                    }]
                }
            except Exception as e:
                return {
                    "content": [{
                        "type": "text",
                        "text": f"Error listing files: {str(e)}"
                    }]
                }
        
        else:
            return {
                "content": [{
                    "type": "text",
                    "text": f"Unknown tool: {name}"
                }]
            }
    
    async def process_request(self, request: Dict[str, Any]) -> Optional[Dict[str, Any]]:
        """Process a JSON-RPC request and return a response"""
        method = request.get("method")
        params = request.get("params", {})
        request_id = request.get("id")
        
        print(f"[SERVER] Processing: method={method}, id={request_id}", file=sys.stderr)
        
        try:
            result = None
            
            if method == "initialize":
                result = await self.handle_initialize(params)
            elif method == "tools/list":
                result = await self.handle_list_tools()
            elif method == "tools/call":
                name = params.get("name", "")
                arguments = params.get("arguments", {})
                result = await self.handle_call_tool(name, arguments)
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
            print(f"[SERVER] Error: {e}", file=sys.stderr)
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
    
    def send_message(self, message: Dict[str, Any]):
        """Send a JSON-RPC message over stdout"""
        json_str = json.dumps(message)
        output = f"Content-Length: {len(json_str)}\r\n\r\n{json_str}"
        sys.stdout.write(output)
        sys.stdout.flush()
    
    async def read_message(self) -> Optional[Dict[str, Any]]:
        """Read a JSON-RPC message from stdin"""
        try:
            header_line = sys.stdin.readline()
            if not header_line:
                return None
            
            if "Content-Length:" not in header_line:
                print(f"[SERVER] Invalid header: {header_line.strip()}", file=sys.stderr)
                return None
            
            content_length = int(header_line.split(":", 1)[1].strip())
            
            # Read empty line
            sys.stdin.readline()
            
            # Read JSON content
            content = sys.stdin.read(content_length)
            if not content:
                return None
            
            message = json.loads(content)
            return message
            
        except Exception as e:
            print(f"[SERVER] Error reading message: {e}", file=sys.stderr)
            return None
    
    async def run(self):
        """Main server loop"""
        print(f"[SERVER] Ready for messages...", file=sys.stderr)
        
        while True:
            try:
                message = await self.read_message()
                if message is None:
                    break
                
                response = await self.process_request(message)
                
                if response:
                    self.send_message(response)
                
            except KeyboardInterrupt:
                break
            except Exception as e:
                print(f"[SERVER] Unexpected error: {e}", file=sys.stderr)
                continue

async def main():
    server = ThalesMCPServer()
    await server.run()

if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print(f"[SERVER] Shutdown complete", file=sys.stderr)
